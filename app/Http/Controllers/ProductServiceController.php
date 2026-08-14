<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryDashboardService;
use App\Domain\Inventory\StockAdjustmentService;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\ProductServiceTax;
use App\Models\ProductServiceUnit;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductServiceController extends Controller
{
    public function dashboard(Request $request, InventoryDashboardService $dashboardService): Response
    {
        $workspace = $this->workspace($request, 'inventory.stock.view');
        $data = $dashboardService->getMetrics($workspace);

        $metrics = [
            'products' => $data['stats']['total_products'],
            'services' => $data['stats']['total_services'],
            'warehouses' => $data['stats']['total_warehouses'],
            'stock_quantity' => $data['stats']['total_stock_units'],
            'low_stock' => $data['stats']['low_stock_items'],
        ];

        return Inertia::render('ProductService/Dashboard', ['metrics' => $metrics] + $data);
    }

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.item.view');
        $items = ProductServiceItem::query()
            ->forTenant($workspace->organization_id, $workspace->id)
            ->with(['category', 'taxes', 'stocks.warehouse'])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%"));
            })
            ->when($request->string('type')->toString(), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->integer('category_id'), function ($query, $catId) {
                $query->where('category_id', $catId);
            })
            ->latest()->paginate(20)->withQueryString();

        $catalog = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id);
        $stock = DB::table('warehouse_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')
            ->join('product_service_items', 'product_service_items.id', '=', 'warehouse_stocks.product_id')
            ->where('warehouses.workspace_id', $workspace->id);

        return Inertia::render('ProductService/Index', [
            'items' => $items,
            'categories' => ProductServiceCategory::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'metrics' => [
                'products' => (clone $catalog)->where('type', 'product')->count(),
                'services' => (clone $catalog)->where('type', 'service')->count(),
                'warehouses' => Warehouse::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->count(),
                'stock_quantity' => (float) (clone $stock)->sum('warehouse_stocks.quantity'),
                'stock_value' => (float) (clone $stock)->sum(DB::raw('warehouse_stocks.quantity * product_service_items.purchase_price')),
                'low_stock' => (clone $stock)->where('warehouse_stocks.quantity', '<=', 5)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.item.create');

        return Inertia::render('ProductService/Create', $this->options($workspace) + ['itemTypes' => $this->itemTypes()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.item.create');
        $data = $this->validatedItem($request, $workspace);
        $taxes = $data['tax_ids'] ?? [];
        unset($data['tax_ids']);
        $item = ProductServiceItem::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id]);
        $item->taxes()->sync($taxes);

        return to_route('product-service.index')->with('success', 'Catalog item created.');
    }

    public function show(Request $request, ProductServiceItem $productService): Response
    {
        $workspace = $this->workspace($request, 'product_service.item.view');
        $this->assertItem($productService, $workspace);

        $productService->load(['category', 'taxes', 'stocks.warehouse']);
        $movements = StockMovement::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('product_id', $productService->id)
            ->with(['warehouse', 'creator'])
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('ProductService/Show', [
            'item' => $productService,
            'movements' => $movements,
        ]);
    }

    public function edit(Request $request, ProductServiceItem $productService): Response
    {
        $workspace = $this->workspace($request, 'product_service.item.update');
        $this->assertItem($productService, $workspace);

        return Inertia::render('ProductService/Edit', $this->options($workspace) + ['itemTypes' => $this->itemTypes(), 'item' => $productService->load('taxes')]);
    }

    public function update(Request $request, ProductServiceItem $productService): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.item.update');
        $this->assertItem($productService, $workspace);
        $data = $this->validatedItem($request, $workspace, $productService);
        $taxes = $data['tax_ids'] ?? [];
        unset($data['tax_ids']);
        $productService->update($data);
        $productService->taxes()->sync($taxes);

        return to_route('product-service.index')->with('success', 'Catalog item updated.');
    }

    public function destroy(Request $request, ProductServiceItem $productService): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.item.delete');
        $this->assertItem($productService, $workspace);

        // Deletion guard: reject if stock exists or movements exist
        if ($productService->stocks()->where('quantity', '>', 0)->exists()) {
            abort(422, 'Cannot delete a product with active warehouse stock. Adjust stock to zero first.');
        }

        if (StockMovement::where('product_id', $productService->id)->exists()) {
            abort(422, 'Cannot delete a product with historical inventory movements.');
        }

        $productService->taxes()->detach();
        $productService->delete();

        return to_route('product-service.index')->with('success', 'Catalog item deleted.');
    }

    public function stockIndex(Request $request): Response
    {
        $workspace = $this->workspace($request, 'inventory.stock.view');
        $stocks = WarehouseStock::query()
            ->whereHas('warehouse', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with(['product.category', 'warehouse'])
            ->when($request->integer('warehouse_id'), fn ($q, $whId) => $q->where('warehouse_id', $whId))
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            })
            ->paginate(25)->withQueryString();

        $warehouses = Warehouse::forWorkspace($workspace->organization_id, $workspace->id)->where('is_active', true)->get();

        return Inertia::render('ProductService/Stock/Index', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
        ]);
    }

    public function adjust(Request $request, ProductServiceItem $productService, StockAdjustmentService $stocks): RedirectResponse
    {
        $workspace = $this->workspace($request, 'inventory.stock.adjust');
        $this->assertItem($productService, $workspace);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $warehouse = Warehouse::query()->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->findOrFail($data['warehouse_id']);
        $stocks->adjust($productService, $warehouse, (float) $data['quantity'], $data['reason'], $request->user(), 'adjusted');

        return back()->with('success', 'Stock adjusted.');
    }

    // --- Category CRUD ---
    public function categoriesIndex(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.category.view');
        $categories = ProductServiceCategory::forWorkspace($workspace->organization_id, $workspace->id)
            ->withCount('items')
            ->latest()
            ->get();

        return Inertia::render('ProductService/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.category.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['product', 'service'])],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        ProductServiceCategory::create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'product',
            'color' => $data['color'] ?? '#3b82f6',
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Category created successfully.');
    }

    public function updateCategory(Request $request, ProductServiceCategory $category): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.category.update');
        $this->assertCategory($category, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['product', 'service'])],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(Request $request, ProductServiceCategory $category): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.category.delete');
        $this->assertCategory($category, $workspace);

        if ($category->items()->exists()) {
            abort(422, 'Cannot delete a category with active catalog items.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

    // --- Unit CRUD ---
    public function unitsIndex(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.unit.view');
        $units = ProductServiceUnit::forWorkspace($workspace->organization_id, $workspace->id)
            ->withCount('items')
            ->latest()
            ->get();

        return Inertia::render('ProductService/Units/Index', [
            'units' => $units,
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.unit.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:32'],
        ]);

        ProductServiceUnit::firstOrCreate(
            ['name' => $data['name'], 'workspace_id' => $workspace->id],
            $data + ['organization_id' => $workspace->organization_id]
        );

        return back()->with('success', 'Unit saved successfully.');
    }

    public function updateUnit(Request $request, ProductServiceUnit $unit): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.unit.update');
        $this->assertUnit($unit, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:32'],
        ]);

        $unit->update($data);

        return back()->with('success', 'Unit updated successfully.');
    }

    public function destroyUnit(Request $request, ProductServiceUnit $unit): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.unit.delete');
        $this->assertUnit($unit, $workspace);

        if ($unit->items()->exists()) {
            abort(422, 'Cannot delete a unit currently assigned to catalog items.');
        }

        $unit->delete();

        return back()->with('success', 'Unit deleted successfully.');
    }

    // --- Tax CRUD ---
    public function taxesIndex(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.tax.view');
        $taxes = ProductServiceTax::forWorkspace($workspace->organization_id, $workspace->id)
            ->withCount('items')
            ->latest()
            ->get();

        return Inertia::render('ProductService/Taxes/Index', [
            'taxes' => $taxes,
        ]);
    }

    public function storeTax(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.tax.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_compound' => ['boolean'],
        ]);

        ProductServiceTax::firstOrCreate(
            ['name' => $data['name'], 'workspace_id' => $workspace->id],
            $data + ['organization_id' => $workspace->organization_id]
        );

        return back()->with('success', 'Tax saved successfully.');
    }

    public function updateTax(Request $request, ProductServiceTax $tax): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.tax.update');
        $this->assertTax($tax, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_compound' => ['boolean'],
        ]);

        $tax->update($data);

        return back()->with('success', 'Tax updated successfully.');
    }

    public function destroyTax(Request $request, ProductServiceTax $tax): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.tax.delete');
        $this->assertTax($tax, $workspace);

        if ($tax->items()->exists()) {
            abort(422, 'Cannot delete a tax currently attached to catalog items.');
        }

        $tax->delete();

        return back()->with('success', 'Tax deleted successfully.');
    }

    // --- Movement History & Reports ---
    public function movements(Request $request): Response
    {
        $workspace = $this->workspace($request, 'inventory.movement.view');

        $movements = StockMovement::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['product', 'warehouse', 'creator'])
            ->when($request->integer('warehouse_id'), fn ($q, $whId) => $q->where('warehouse_id', $whId))
            ->when($request->integer('product_id'), fn ($q, $pId) => $q->where('product_id', $pId))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $warehouses = Warehouse::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $products = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('type', 'product')->get();

        return Inertia::render('ProductService/Movements/Index', [
            'movements' => $movements,
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }

    // --- JSON API ---
    public function apiIndex(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request, 'product_service.item.view');

        $items = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)
            ->with(['category', 'taxes', 'stocks'])
            ->where('is_active', true)
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    // --- Private / Protected Helpers ---
    private function assertItem(ProductServiceItem $item, Workspace $workspace): void
    {
        abort_if((int) $item->workspace_id !== (int) $workspace->id || (int) $item->organization_id !== (int) $workspace->organization_id, 404);
    }

    private function assertCategory(ProductServiceCategory $category, Workspace $workspace): void
    {
        abort_if((int) $category->workspace_id !== (int) $workspace->id || (int) $category->organization_id !== (int) $workspace->organization_id, 404);
    }

    private function assertUnit(ProductServiceUnit $unit, Workspace $workspace): void
    {
        abort_if((int) $unit->workspace_id !== (int) $workspace->id || (int) $unit->organization_id !== (int) $workspace->organization_id, 404);
    }

    private function assertTax(ProductServiceTax $tax, Workspace $workspace): void
    {
        abort_if((int) $tax->workspace_id !== (int) $workspace->id || (int) $tax->organization_id !== (int) $workspace->organization_id, 404);
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->findOrFail($request->session()->get('active_workspace_id'));
        $user = $request->user();
        abort_unless($user, 401);

        $legacy = str_starts_with($permission, 'inventory.') ? 'inventory.manage' : 'product_service.manage';

        abort_unless(
            $user->canInWorkspace($permission, $workspace)
                || $user->canInWorkspace($legacy, $workspace)
                || ($permission === 'inventory.stock.adjust' && $user->canInWorkspace('inventory.adjust', $workspace)),
            403,
            'Permission denied'
        );

        return $workspace;
    }

    private function options(Workspace $workspace): array
    {
        return [
            'categories' => ProductServiceCategory::query()->where('workspace_id', $workspace->id)->get(['id', 'name', 'type', 'color']),
            'units' => ProductServiceUnit::query()->where('workspace_id', $workspace->id)->get(['id', 'name', 'symbol']),
            'taxes' => ProductServiceTax::query()->where('workspace_id', $workspace->id)->get(['id', 'name', 'rate', 'is_compound']),
            'warehouses' => Warehouse::query()->where('workspace_id', $workspace->id)->where('is_active', true)->get(['id', 'name']),
        ];
    }

    private function validatedItem(Request $request, Workspace $workspace, ?ProductServiceItem $item = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('product_service_items', 'sku')->where('workspace_id', $workspace->id)->ignore($item?->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('product_service_items', 'barcode')->where('workspace_id', $workspace->id)->ignore($item?->id)],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['product', 'service'])],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:64'],
            'category_id' => ['nullable', Rule::exists('product_service_categories', 'id')->where('workspace_id', $workspace->id)],
            'tax_ids' => ['nullable', 'array'],
            'tax_ids.*' => [Rule::exists('product_service_taxes', 'id')->where('workspace_id', $workspace->id)],
            'is_active' => ['boolean'],
        ]);
    }

    private function itemTypes(): array
    {
        return [
            ['value' => 'product', 'label' => 'Product'],
            ['value' => 'service', 'label' => 'Service'],
        ];
    }
}
