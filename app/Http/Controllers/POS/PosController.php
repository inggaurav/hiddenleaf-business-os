<?php

namespace App\Http\Controllers\POS;

use App\Domain\POS\PosCheckoutService;
use App\Domain\POS\PosNumberService;
use App\Http\Controllers\Controller;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        private readonly PosCheckoutService $checkoutService,
        private readonly PosNumberService $numberService,
    ) {}

    public function index(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $query = PosSale::with(['cashier:id,name', 'counter:id,name'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%");
            });
        }

        if ($request->filled('counter_id')) {
            $query->where('billing_counter_id', $request->input('counter_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        return Inertia::render('POS/Index', [
            'sales' => $query->latest()->paginate(15)->withQueryString(),
            'counters' => BillingCounter::where('organization_id', $orgId)->where('workspace_id', $wsId)->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        return Inertia::render('POS/Create', [
            'counters' => BillingCounter::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('is_active', true)
                ->get(),
            'warehouses' => Warehouse::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('is_active', true)
                ->get(),
            'discounts' => PosDiscount::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('is_active', true)
                ->get(),
            // Stable for the lifetime of this rendered checkout form. A browser
            // double-submit reuses it; a fresh checkout page gets a new token.
            'checkout_token' => (string) Str::uuid(),
        ]);
    }

    public function getProducts(Request $request): JsonResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $search = $request->input('query');

        if ($warehouseId) {
            Warehouse::query()
                ->where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->whereKey($warehouseId)
                ->firstOrFail();
        }

        $productsQuery = ProductServiceItem::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true);

        if (! empty($search)) {
            $productsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->get()->map(function ($product) use ($warehouseId) {
            $stock = '0.0000';
            if ($product->type === 'product' && $warehouseId) {
                $stock = (string) (WarehouseStock::where('warehouse_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->value('quantity') ?? '0.0000');
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'type' => $product->type,
                'sale_price' => (string) $product->sale_price,
                'tax_rate' => (string) ($product->tax_rate ?? '0'),
                'stock' => $stock,
            ];
        });

        return response()->json($products);
    }

    public function getNextPosNumber(Request $request): JsonResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');

        return response()->json(['next_number' => $this->numberService->next($wsId)]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->filled('idempotency_key')) {
            $request->merge(['idempotency_key' => $request->header('Idempotency-Key') ?: (string) Str::uuid()]);
        }

        $validated = $request->validate([
            'billing_counter_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'discount_id' => ['nullable', 'integer'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'bank'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $sale = $this->checkoutService->checkout(
            (int) $request->session()->get('active_workspace_id'),
            (int) $request->session()->get('active_organization_id'),
            $request->user(),
            $validated,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sale' => $sale->load(['items', 'counter', 'journalEntry']),
            ]);
        }

        return redirect()->route('pos.show', $sale->id)->with('success', 'Sale recorded successfully.');
    }

    public function show(Request $request, int|string $sale): Response
    {
        $record = PosSale::with(['items', 'counter', 'cashier', 'returns.items', 'journalEntry'])
            ->where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'))
            ->where('id', $sale)
            ->firstOrFail();

        return Inertia::render('POS/Show', ['sale' => $record]);
    }

    public function barcode(Request $request): Response
    {
        $products = ProductServiceItem::where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'))
            ->where('type', 'product')
            ->whereNotNull('barcode')
            ->get();

        return Inertia::render('POS/Print', ['products' => $products, 'type' => 'barcode_list']);
    }

    public function printBarcode(Request $request, int|string $sale): Response
    {
        $record = $this->saleForTenant($request, $sale, ['items']);

        return Inertia::render('POS/Print', ['sale' => $record, 'type' => 'barcode']);
    }

    public function print(Request $request, int|string $sale): Response
    {
        $record = $this->saleForTenant($request, $sale, ['items', 'counter', 'cashier']);

        return Inertia::render('POS/Print', ['sale' => $record, 'type' => 'receipt']);
    }

    private function saleForTenant(Request $request, int|string $sale, array $relations = []): PosSale
    {
        return PosSale::with($relations)
            ->where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'))
            ->where('id', $sale)
            ->firstOrFail();
    }
}
