<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\StockAdjustmentService;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\ProductServiceTax;
use App\Models\ProductServiceUnit;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\Inventory\InventoryDashboardService;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductServiceController extends Controller
{
    public function dashboard(Request $request, InventoryDashboardService $dashboardService): Response
    {
        $workspace = $this->workspace($request, 'product_service.manage');
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
        $workspace = $this->workspace($request, 'product_service.manage');
        $items = ProductServiceItem::query()
            ->forTenant($workspace->organization_id, $workspace->id)
            ->with(['category', 'taxes', 'stocks.warehouse'])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%"));
            })
            ->latest()->paginate(20)->withQueryString();

        $catalog = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id);
        $stock = DB::table('warehouse_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')
            ->join('product_service_items', 'product_service_items.id', '=', 'warehouse_stocks.product_id')
            ->where('warehouses.workspace_id', $workspace->id);

        return Inertia::render('ProductService/Index', ['items' => $items, 'metrics' => ['products' => (clone $catalog)->where('type', 'product')->count(), 'services' => (clone $catalog)->where('type', 'service')->count(), 'warehouses' => Warehouse::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->count(), 'stock_quantity' => (float) (clone $stock)->sum('warehouse_stocks.quantity'), 'stock_value' => (float) (clone $stock)->sum(DB::raw('warehouse_stocks.quantity * product_service_items.purchase_price')), 'low_stock' => (clone $stock)->where('warehouse_stocks.quantity', '<=', 5)->count()]]);
    }

    public function create(Request $request): Response
    {
        $workspace = $this->workspace($request, 'product_service.manage');

        return Inertia::render('ProductService/Create', $this->options($workspace) + ['itemTypes' => $this->itemTypes()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.manage');
        $data = $this->validatedItem($request, $workspace);
        $taxes = $data['tax_ids'] ?? [];
        unset($data['tax_ids']);
        $item = ProductServiceItem::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id]);
        $item->taxes()->sync($taxes);

        return to_route('product-service.index')->with('success', 'Catalog item created.');
    }

    public function edit(Request $request, ProductServiceItem $productService): Response
    {
        $workspace = $this->workspace($request, 'product_service.manage');
        $this->assertItem($productService, $workspace);

        return Inertia::render('ProductService/Edit', $this->options($workspace) + ['itemTypes' => $this->itemTypes(), 'item' => $productService->load('taxes')]);
    }

    public function update(Request $request, ProductServiceItem $productService): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.manage');
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
        $workspace = $this->workspace($request, 'product_service.manage');
        $this->assertItem($productService, $workspace);
        abort_if($productService->stocks()->where('quantity', '>', 0)->exists(), 422, 'Products with stock cannot be deleted.');
        $productService->delete();

        return to_route('product-service.index')->with('success', 'Catalog item deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['product', 'service'])]]);
        ProductServiceCategory::firstOrCreate($data + ['workspace_id' => $workspace->id], ['organization_id' => $workspace->organization_id, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Category saved.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'symbol' => ['required', 'string', 'max:32']]);
        ProductServiceUnit::firstOrCreate(['name' => $data['name'], 'workspace_id' => $workspace->id], $data + ['organization_id' => $workspace->organization_id]);

        return back()->with('success', 'Unit saved.');
    }

    public function storeTax(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'product_service.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'rate' => ['required', 'numeric', 'min:0', 'max:100'], 'is_compound' => ['boolean']]);
        ProductServiceTax::firstOrCreate(['name' => $data['name'], 'workspace_id' => $workspace->id], $data + ['organization_id' => $workspace->organization_id]);

        return back()->with('success', 'Tax saved.');
    }

    public function adjust(Request $request, ProductServiceItem $productService, StockAdjustmentService $stocks): RedirectResponse
    {
        $workspace = $this->workspace($request, 'inventory.adjust');
        $this->assertItem($productService, $workspace);
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'not_in:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $warehouse = Warehouse::query()->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->findOrFail($data['warehouse_id']);
        $stocks->adjust($productService, $warehouse, (float) $data['quantity'], $data['reason'], $request->user());

        return back()->with('success', 'Inventory adjusted.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }

    private function assertItem(ProductServiceItem $item, Workspace $workspace): void
    {
        abort_unless((int) $item->organization_id === (int) $workspace->organization_id && (int) $item->workspace_id === (int) $workspace->id, 404);
    }

    private function validatedItem(Request $request, Workspace $workspace, ?ProductServiceItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('product_service_items')->where('workspace_id', $workspace->id)->ignore($item)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('product_service_items')->where('workspace_id', $workspace->id)->ignore($item)],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['product', 'service'])],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'tax_ids' => ['array'],
            'tax_ids.*' => ['integer'],
            'is_active' => ['boolean'],
        ]);
        if (! empty($data['category_id'])) {
            abort_unless(ProductServiceCategory::where('workspace_id', $workspace->id)->whereKey($data['category_id'])->exists(), 422);
        }
        if (! empty($data['tax_ids'])) {
            abort_unless(ProductServiceTax::where('workspace_id', $workspace->id)->whereKey($data['tax_ids'])->count() === count(array_unique($data['tax_ids'])), 422);
        }

        return $data;
    }

    private function options(Workspace $workspace): array
    {
        return [
            'categories' => ProductServiceCategory::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'units' => ProductServiceUnit::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'taxes' => ProductServiceTax::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('workspace_id', $workspace->id)->orderBy('name')->get(),
        ];
    }

    private function itemTypes(): array
    {
        return [['value' => 'product', 'label' => 'Product'], ['value' => 'service', 'label' => 'Service']];
    }
}
