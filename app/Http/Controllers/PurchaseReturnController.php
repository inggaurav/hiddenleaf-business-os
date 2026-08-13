<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseReturnController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $returns = PurchaseReturn::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('PurchaseReturns/Index', [
            'returns' => $returns,
        ]);
    }

    public function create()
    {
        return Inertia::render('PurchaseReturns/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'nullable|integer',
            'purchase_invoice_id' => 'nullable|integer',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        return DB::transaction(function () use ($validated, $wsId, $orgId) {
            $returnId = strtoupper(substr(uniqid('PR-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $return = PurchaseReturn::create([
                'return_id' => $returnId,
                'vendor_id' => $validated['vendor_id'] ?? null,
                'purchase_invoice_id' => $validated['purchase_invoice_id'] ?? null,
                'date' => $validated['date'],
                'total_amount' => $total,
                'status' => 0, // Pending
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return redirect()->route('purchase-returns.index')->with('success', 'Purchase return recorded.');
        });
    }

    public function show(PurchaseReturn $return)
    {
        $return->load(['items']);

        return Inertia::render('PurchaseReturns/Show', [
            'return' => $return,
        ]);
    }

    public function destroy(PurchaseReturn $return)
    {
        $return->items()->delete();
        $return->delete();

        return redirect()->route('purchase-returns.index')->with('success', 'Purchase return deleted.');
    }

    public function approve(PurchaseReturn $return)
    {
        $return->update(['status' => 1]); // Approved

        return back()->with('success', 'Purchase return approved.');
    }

    public function complete(PurchaseReturn $return)
    {
        $return->update(['status' => 2]); // Completed

        return back()->with('success', 'Purchase return completed.');
    }
}
