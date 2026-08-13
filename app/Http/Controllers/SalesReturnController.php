<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoiceReturn;
use App\Models\SalesInvoiceReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $returns = SalesInvoiceReturn::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('SalesReturns/Index', [
            'returns' => $returns,
        ]);
    }

    public function create()
    {
        return Inertia::render('SalesReturns/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'sales_invoice_id' => 'nullable|integer',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        return DB::transaction(function () use ($validated, $wsId, $orgId) {
            $returnId = strtoupper(substr(uniqid('SR-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $return = SalesInvoiceReturn::create([
                'return_id' => $returnId,
                'customer_id' => $validated['customer_id'] ?? null,
                'sales_invoice_id' => $validated['sales_invoice_id'] ?? null,
                'date' => $validated['date'],
                'total_amount' => $total,
                'status' => 0, // Pending
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                SalesInvoiceReturnItem::create([
                    'sales_invoice_return_id' => $return->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return redirect()->route('sales-returns.index')->with('success', 'Sales return recorded.');
        });
    }

    public function show(SalesInvoiceReturn $salesReturn)
    {
        $salesReturn->load(['items']);

        return Inertia::render('SalesReturns/Show', [
            'return' => $salesReturn,
        ]);
    }

    public function destroy(SalesInvoiceReturn $salesReturn)
    {
        $salesReturn->items()->delete();
        $salesReturn->delete();

        return redirect()->route('sales-returns.index')->with('success', 'Sales return deleted.');
    }

    public function approve(SalesInvoiceReturn $salesReturn)
    {
        $salesReturn->update(['status' => 1]); // Approved

        return back()->with('success', 'Sales return approved.');
    }

    public function complete(SalesInvoiceReturn $salesReturn)
    {
        $salesReturn->update(['status' => 2]); // Completed

        return back()->with('success', 'Sales return completed.');
    }
}
