<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

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

    public function create()
    {
        $wsId = session('active_workspace_id');
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();

        return Inertia::render('PurchaseInvoices/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'nullable|integer',
            'warehouse_id' => 'required|exists:warehouses,id',
            'purchase_date' => 'required|date',
            'due_date' => 'nullable|date',
            'category_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        return DB::transaction(function () use ($validated, $wsId, $orgId) {
            $invoiceId = strtoupper(substr(uniqid('PI-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
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
                PurchaseInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice created.');
        });
    }

    public function show(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load(['items', 'warehouse']);

        return Inertia::render('PurchaseInvoices/Show', [
            'invoice' => $purchaseInvoice,
        ]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice)
    {
        $wsId = session('active_workspace_id');
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();
        $purchaseInvoice->load(['items']);

        return Inertia::render('PurchaseInvoices/Edit', [
            'invoice' => $purchaseInvoice,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'purchase_date' => 'required|date',
            'due_date' => 'nullable|date',
        ]);

        $purchaseInvoice->update($validated);

        return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice updated.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->items()->delete();
        $purchaseInvoice->delete();

        return redirect()->route('purchase-invoices.index')->with('success', 'Purchase invoice deleted.');
    }

    public function post(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->update(['status' => 1]); // Posted

        return back()->with('success', 'Purchase invoice posted.');
    }

    public function print(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load(['items', 'warehouse']);

        return view('print.purchase_invoice', ['invoice' => $purchaseInvoice]);
    }
}
