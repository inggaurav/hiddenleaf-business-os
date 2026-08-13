<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $transfers = Transfer::with(['fromWarehouse', 'toWarehouse'])
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
        ]);
    }

    public function create()
    {
        $wsId = session('active_workspace_id');
        $warehouses = Warehouse::where('workspace_id', $wsId)->get();

        return Inertia::render('Transfers/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse' => 'required|exists:warehouses,id',
            'to_warehouse' => 'required|different:from_warehouse|exists:warehouses,id',
            'product_id' => 'nullable|integer',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        Transfer::create([
            'from_warehouse' => $validated['from_warehouse'],
            'to_warehouse' => $validated['to_warehouse'],
            'product_id' => $validated['product_id'] ?? null,
            'quantity' => $validated['quantity'],
            'date' => $validated['date'],
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('transfers.index')->with('success', 'Transfer recorded successfully.');
    }

    public function show(Transfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse']);

        return Inertia::render('Transfers/Show', [
            'transfer' => $transfer,
        ]);
    }

    public function destroy(Transfer $transfer)
    {
        $transfer->delete();

        return redirect()->route('transfers.index')->with('success', 'Transfer deleted.');
    }
}
