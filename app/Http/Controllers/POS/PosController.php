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

        $sales = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('POS/Index', [
            'sales' => $sales,
            'counters' => BillingCounter::where('organization_id', $orgId)->where('workspace_id', $wsId)->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $counters = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true)
            ->get();

        $warehouses = Warehouse::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->get();

        $discounts = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true)
            ->get();

        return Inertia::render('POS/Create', [
            'counters' => $counters,
            'warehouses' => $warehouses,
            'discounts' => $discounts,
        ]);
    }

    public function getProducts(Request $request): JsonResponse
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');
        $warehouseId = $request->input('warehouse_id');
        $query = $request->input('query');

        $productsQuery = ProductServiceItem::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true);

        if (!empty($query)) {
            $productsQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            });
        }

        $products = $productsQuery->get()->map(function ($p) use ($warehouseId) {
            $stock = 0.0;
            if ($p->type === 'product' && $warehouseId) {
                $stock = (float) (WarehouseStock::where('warehouse_id', $warehouseId)
                    ->where('product_id', $p->id)
                    ->value('quantity') ?? 0);
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'type' => $p->type,
                'sale_price' => (float) $p->sale_price,
                'tax_rate' => (float) ($p->tax_rate ?? 0),
                'stock' => $stock,
            ];
        });

        return response()->json($products);
    }

    public function getNextPosNumber(Request $request): JsonResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $number = $this->numberService->next($wsId);

        return response()->json(['next_number' => $number]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'billing_counter_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'discount_id' => 'nullable|integer',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        $data = $validated;
        $data['idempotency_key'] = $validated['idempotency_key'] ?? (string) Str::uuid();

        $sale = $this->checkoutService->checkout($wsId, $orgId, $user, $data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sale' => $sale->load(['items', 'counter']),
            ]);
        }

        return redirect()->route('pos.show', $sale->id)->with('success', 'Sale recorded successfully.');
    }

    public function show(Request $request, int|string $sale): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $record = PosSale::with(['items', 'counter', 'cashier', 'returns.items'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $sale)
            ->firstOrFail();

        return Inertia::render('POS/Show', [
            'sale' => $record,
        ]);
    }

    public function barcode(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $products = ProductServiceItem::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('type', 'product')
            ->whereNotNull('barcode')
            ->get();

        return Inertia::render('POS/Print', [
            'products' => $products,
            'type' => 'barcode_list',
        ]);
    }

    public function printBarcode(Request $request, int|string $sale): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $record = PosSale::with('items')
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $sale)
            ->firstOrFail();

        return Inertia::render('POS/Print', [
            'sale' => $record,
            'type' => 'barcode',
        ]);
    }

    public function print(Request $request, int|string $sale): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $record = PosSale::with(['items', 'counter', 'cashier'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $sale)
            ->firstOrFail();

        return Inertia::render('POS/Print', [
            'sale' => $record,
            'type' => 'receipt',
        ]);
    }
}
