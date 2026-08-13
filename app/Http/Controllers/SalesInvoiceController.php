<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

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
        ]);
    }

    public function create()
    {
        $wsId = session('active_workspace_id');
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();

        return Inertia::render('SalesInvoices/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'warehouse_id' => 'required|exists:warehouses,id',
            'issue_date' => 'required|date',
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
            $invoiceId = strtoupper(substr(uniqid('SI-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
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
                SalesInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice created.');
        });
    }

    public function show(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load(['items', 'warehouse']);

        return Inertia::render('SalesInvoices/Show', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function edit(SalesInvoice $salesInvoice)
    {
        $wsId = session('active_workspace_id');
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();
        $salesInvoice->load(['items']);

        return Inertia::render('SalesInvoices/Edit', [
            'invoice' => $salesInvoice,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(Request $request, SalesInvoice $salesInvoice)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
        ]);

        $salesInvoice->update($validated);

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice updated.');
    }

    public function destroy(SalesInvoice $salesInvoice)
    {
        $salesInvoice->items()->delete();
        $salesInvoice->delete();

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice deleted.');
    }

    public function post(SalesInvoice $salesInvoice)
    {
        $salesInvoice->update(['status' => 1]); // Posted

        return back()->with('success', 'Sales invoice posted.');
    }

    public function print(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load(['items', 'warehouse']);

        return view('print.sales_invoice', ['invoice' => $salesInvoice]);
    }

    public function getWarehouseProducts(Request $request)
    {
        return response()->json([]);
    }

    public function getServices(Request $request)
    {
        return response()->json([]);
    }
}
