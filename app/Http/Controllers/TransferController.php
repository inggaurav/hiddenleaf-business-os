<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryTransferService;
use App\Models\ProductServiceItem;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $transfers = Transfer::with(['fromWarehouse', 'toWarehouse'])
            ->where('workspace_id', $workspace->id)
            ->where('organization_id', $workspace->organization_id)
            ->latest()->paginate($request->input('per_page', 10))->withQueryString();

        return Inertia::render('Transfers/Index', ['transfers' => $transfers]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('Transfers/Create', [
            'warehouses' => Warehouse::where('workspace_id', $workspace->id)->get(),
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('type', 'product')->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, InventoryTransferService $transfers)
    {
        $validated = $request->validate([
            'from_warehouse' => 'required|integer',
            'to_warehouse' => 'required|integer|different:from_warehouse',
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|gt:0',
            'date' => 'required|date',
        ]);
        $workspace = $this->workspace($request);
        $warehouses = Warehouse::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->whereIn('id', [$validated['from_warehouse'], $validated['to_warehouse']])->get()->keyBy('id');
        abort_unless($warehouses->count() === 2, 404);
        $product = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('type', 'product')->findOrFail($validated['product_id']);
        $transfers->transfer($warehouses[$validated['from_warehouse']], $warehouses[$validated['to_warehouse']], $product, (float) $validated['quantity'], $validated['date'], $request->user());

        return to_route('transfers.index')->with('success', 'Inventory transferred.');
    }

    public function show(Request $request, Transfer $transfer)
    {
        $this->assertTransfer($transfer, $this->workspace($request));

        return Inertia::render('Transfers/Show', ['transfer' => $transfer->load(['fromWarehouse', 'toWarehouse'])]);
    }

    public function destroy(Request $request, Transfer $transfer)
    {
        $this->assertTransfer($transfer, $this->workspace($request));
        abort(422, 'Completed inventory transfers are immutable.');
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('inventory.adjust', $workspace), 403);

        return $workspace;
    }

    private function assertTransfer(Transfer $transfer, Workspace $workspace): void
    {
        abort_unless((int) $transfer->organization_id === (int) $workspace->organization_id && (int) $transfer->workspace_id === (int) $workspace->id, 404);
    }
}
