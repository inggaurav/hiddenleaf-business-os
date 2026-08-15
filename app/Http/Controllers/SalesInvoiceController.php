<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InvoicePostingService;
use App\Domain\ProductService\Services\CatalogLookupService;
use App\Domain\Sales\SalesDashboardService;
use App\Domain\Shared\DocumentNumberService;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesInvoiceController extends Controller
{
    public function dashboard(Request $request, SalesDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request);
        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('SalesInvoices/Dashboard', $data);
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        $invoices = SalesInvoice::with(['warehouse'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->search, fn ($q) => $q->where('invoice_id', 'like', "%{$request->search}%"))
            ->when($request->status !== null, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('SalesInvoices/Index', [
            'invoices' => $invoices,
            'metrics' => [
                'invoices' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->count(),
                'draft' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 0)->count(),
                'posted' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', [1, 2, 3])->count(),
                'paid' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 3)->count(),
                'revenue' => (float) SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', [1, 2, 3])->sum('total_amount'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();

        return Inertia::render('SalesInvoices/Create', [
            'warehouses' => $warehouses,
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'warehouse_id' => 'required|integer',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'category_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.item_name' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;
        Warehouse::where('workspace_id', $wsId)->where('organization_id', $orgId)->findOrFail($validated['warehouse_id']);

        $productIds = collect($validated['items'])->pluck('product_id')->filter()->unique();
        if ($productIds->isNotEmpty()) {
            $products = ProductServiceItem::forTenant($orgId, $wsId)->whereIn('id', $productIds)->get()->keyBy('id');
            abort_unless($products->count() === $productIds->count(), 422, 'An invoice item is outside the active tenant catalog.');
        } else {
            $products = collect();
        }

        return DB::transaction(function () use ($validated, $wsId, $orgId, $products) {
            $invoiceId = app(DocumentNumberService::class)->next($wsId, 'sales_invoice', 'SI');

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += ($item['quantity'] * $item['price']) + ($item['tax'] ?? 0) - ($item['discount'] ?? 0);
            }

            $invoice = SalesInvoice::create([
                'invoice_id' => $invoiceId,
                'customer_id' => $validated['customer_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'total_amount' => $total,
                'status' => 0, // Draft
                'category_id' => $validated['category_id'] ?? null,
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                $itemName = isset($item['product_id']) && isset($products[$item['product_id']])
                    ? $products[$item['product_id']]->name
                    : ($item['item_name'] ?? 'Invoice Item');

                SalesInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $itemName,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice created.');
        });
    }

    public function show(Request $request, SalesInvoice $salesInvoice)
    {
        $this->assertInvoice($salesInvoice, $this->workspace($request));
        $salesInvoice->load(['items', 'warehouse']);

        return Inertia::render('SalesInvoices/Show', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function edit(Request $request, SalesInvoice $salesInvoice)
    {
        $workspace = $this->workspace($request);
        $this->assertInvoice($salesInvoice, $workspace);
        $wsId = $workspace->id;
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();
        $salesInvoice->load(['items']);

        return Inertia::render('SalesInvoices/Edit', [
            'invoice' => $salesInvoice,
            'warehouses' => $warehouses,
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, SalesInvoice $salesInvoice)
    {
        $workspace = $this->workspace($request);
        $this->assertInvoice($salesInvoice, $workspace);
        abort_unless((int) $salesInvoice->status === 0, 422, 'Posted invoices cannot be edited.');
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
        ]);
        Warehouse::where('workspace_id', $workspace->id)->where('organization_id', $workspace->organization_id)->findOrFail($validated['warehouse_id']);

        $salesInvoice->update($validated);

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice updated.');
    }

    public function destroy(Request $request, SalesInvoice $salesInvoice)
    {
        $this->assertInvoice($salesInvoice, $this->workspace($request));
        abort_unless((int) $salesInvoice->status === 0, 422, 'Posted invoices cannot be deleted.');
        $salesInvoice->items()->delete();
        $salesInvoice->delete();

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice deleted.');
    }

    public function post(Request $request, SalesInvoice $salesInvoice, InvoicePostingService $posting)
    {
        $this->assertInvoice($salesInvoice, $this->workspace($request));
        $posting->postSale($salesInvoice, $request->user());

        return back()->with('success', 'Sales invoice posted.');
    }

    public function print(Request $request, SalesInvoice $salesInvoice)
    {
        $this->assertInvoice($salesInvoice, $this->workspace($request));
        $salesInvoice->load(['items', 'warehouse']);

        return view('print.sales_invoice', ['invoice' => $salesInvoice]);
    }

    public function getWarehouseProducts(Request $request, CatalogLookupService $catalog)
    {
        $workspace = $this->workspace($request);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
        ]);

        return response()->json($catalog->productsForWarehouse(
            (int) $validated['warehouse_id'],
            (int) $workspace->organization_id,
            (int) $workspace->id,
        ));
    }

    public function getServices(Request $request, CatalogLookupService $catalog)
    {
        $workspace = $this->workspace($request);

        return response()->json($catalog->services(
            (int) $workspace->organization_id,
            (int) $workspace->id,
        ));
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('sales.manage', $workspace), 403);

        return $workspace;
    }

    private function assertInvoice(SalesInvoice $invoice, Workspace $workspace): void
    {
        abort_unless((int) $invoice->organization_id === (int) $workspace->organization_id && (int) $invoice->workspace_id === (int) $workspace->id, 404);
    }
}
