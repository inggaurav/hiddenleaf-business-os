<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InvoicePostingService;
use App\Domain\Procurement\ProcurementDashboardService;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    public function dashboard(Request $request, ProcurementDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request);
        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('PurchaseInvoices/Dashboard', $data);
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        $invoices = PurchaseInvoice::with(['warehouse'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->search, fn ($q) => $q->where('invoice_id', 'like', "%{$request->search}%"))
            ->when($request->status !== null, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('PurchaseInvoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();

        return Inertia::render('PurchaseInvoices/Create', [
            'warehouses' => $warehouses,
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'nullable|integer',
            'warehouse_id' => 'required|integer',
            'purchase_date' => 'required|date',
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
            $invoiceId = strtoupper(substr(uniqid('PI-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += ($item['quantity'] * $item['price']) + ($item['tax'] ?? 0) - ($item['discount'] ?? 0);
            }

            $invoice = PurchaseInvoice::create([
                'invoice_id' => $invoiceId,
                'vendor_id' => $validated['vendor_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'purchase_date' => $validated['purchase_date'],
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
                    : ($item['item_name'] ?? 'Purchase Item');

                PurchaseInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $itemName,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice created.');
        });
    }

    public function show(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->assertInvoice($purchaseInvoice, $this->workspace($request));
        $purchaseInvoice->load(['items', 'warehouse']);

        return Inertia::render('PurchaseInvoices/Show', [
            'invoice' => $purchaseInvoice,
        ]);
    }

    public function edit(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $workspace = $this->workspace($request);
        $this->assertInvoice($purchaseInvoice, $workspace);
        $wsId = $workspace->id;
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();
        $purchaseInvoice->load(['items']);

        return Inertia::render('PurchaseInvoices/Edit', [
            'invoice' => $purchaseInvoice,
            'warehouses' => $warehouses,
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $workspace = $this->workspace($request);
        $this->assertInvoice($purchaseInvoice, $workspace);
        abort_unless((int) $purchaseInvoice->status === 0, 422, 'Posted invoices cannot be edited.');
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'purchase_date' => 'required|date',
            'due_date' => 'nullable|date',
        ]);
        Warehouse::where('workspace_id', $workspace->id)->where('organization_id', $workspace->organization_id)->findOrFail($validated['warehouse_id']);

        $purchaseInvoice->update($validated);

        return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice updated.');
    }

    public function destroy(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->assertInvoice($purchaseInvoice, $this->workspace($request));
        abort_unless((int) $purchaseInvoice->status === 0, 422, 'Posted invoices cannot be deleted.');
        $purchaseInvoice->items()->delete();
        $purchaseInvoice->delete();

        return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice deleted.');
    }

    public function post(Request $request, PurchaseInvoice $purchaseInvoice, InvoicePostingService $posting)
    {
        $this->assertInvoice($purchaseInvoice, $this->workspace($request));
        $posting->postPurchase($purchaseInvoice, $request->user());

        return back()->with('success', 'Purchase invoice posted.');
    }

    public function print(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->assertInvoice($purchaseInvoice, $this->workspace($request));
        $purchaseInvoice->load(['items', 'warehouse']);

        return view('print.purchase_invoice', ['invoice' => $purchaseInvoice]);
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('procurement.manage', $workspace), 403);

        return $workspace;
    }

    private function assertInvoice(PurchaseInvoice $invoice, Workspace $workspace): void
    {
        abort_unless((int) $invoice->organization_id === (int) $workspace->organization_id && (int) $invoice->workspace_id === (int) $workspace->id, 404);
    }
}
