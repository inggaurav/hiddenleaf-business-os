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
        $workspace = $this->workspace($request, 'inventory.transfer.view');
        $transfers = Transfer::with(['fromWarehouse', 'toWarehouse', 'product'])
            ->where('workspace_id', $workspace->id)
            ->where('organization_id', $workspace->organization_id)
            ->latest()->paginate($request->input('per_page', 10))->withQueryString();

        return Inertia::render('Transfers/Index', ['transfers' => $transfers]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request, 'inventory.transfer.create');

        return Inertia::render('Transfers/Create', [
            'warehouses' => Warehouse::where('workspace_id', $workspace->id)->where('is_active', true)->get(),
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('type', 'product')->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, InventoryTransferService $transfers)
    {
        $workspace = $this->workspace($request, 'inventory.transfer.create');
        $validated = $request->validate([
            'from_warehouse' => 'required|integer',
            'to_warehouse' => 'required|integer|different:from_warehouse',
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|gt:0',
            'date' => 'required|date',
        ]);

        $warehouses = Warehouse::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', [$validated['from_warehouse'], $validated['to_warehouse']])
            ->get()
            ->keyBy('id');

        abort_unless($warehouses->count() === 2, 404);
        $product = ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('type', 'product')->findOrFail($validated['product_id']);
        $transfers->transfer($warehouses[$validated['from_warehouse']], $warehouses[$validated['to_warehouse']], $product, (float) $validated['quantity'], $validated['date'], $request->user());

        return to_route('transfers.index')->with('success', 'Inventory transferred successfully.');
    }

    public function show(Request $request, Transfer $transfer)
    {
        $workspace = $this->workspace($request, 'inventory.transfer.view');
        $this->assertTransfer($transfer, $workspace);

        return Inertia::render('Transfers/Show', ['transfer' => $transfer->load(['fromWarehouse', 'toWarehouse', 'product', 'creator'])]);
    }

    public function destroy(Request $request, Transfer $transfer)
    {
        $workspace = $this->workspace($request, 'inventory.transfer.delete');
        $this->assertTransfer($transfer, $workspace);
        abort(422, 'Completed inventory transfers are immutable.');
    }

    private function workspace(Request $request, string $permission = 'inventory.transfer.view'): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace, 403);
        $user = $request->user();
        abort_unless($user, 401);

        abort_unless(
            $user->canInWorkspace($permission, $workspace)
                || $user->canInWorkspace('inventory.manage', $workspace)
                || $user->canInWorkspace('inventory.adjust', $workspace),
            403,
            'Permission denied'
        );

        return $workspace;
    }

    private function assertTransfer(Transfer $transfer, Workspace $workspace): void
    {
        abort_unless((int) $transfer->organization_id === (int) $workspace->organization_id && (int) $transfer->workspace_id === (int) $workspace->id, 404);
    }
}
