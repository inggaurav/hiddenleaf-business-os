<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.view');
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        $warehouses = Warehouse::query()
            ->where('workspace_id', $wsId)
            ->where('organization_id', $orgId)
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->withCount('stocks')
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function create(Request $request)
    {
        $this->workspace($request, 'inventory.warehouse.create');

        return Inertia::render('Warehouses/Create');
    }

    public function store(Request $request)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.create');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:32',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'city_zip' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:64',
            'is_active' => 'boolean',
        ]);

        Warehouse::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'city_zip' => $validated['city_zip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
    }

    public function show(Request $request, Warehouse $warehouse)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.view');
        $this->assertWarehouse($warehouse, $workspace);

        return redirect()->route('warehouses.edit', $warehouse);
    }

    public function edit(Request $request, Warehouse $warehouse)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.update');
        $this->assertWarehouse($warehouse, $workspace);

        return Inertia::render('Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.update');
        $this->assertWarehouse($warehouse, $workspace);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:32',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'city_zip' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:64',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        $workspace = $this->workspace($request, 'inventory.warehouse.delete');
        $this->assertWarehouse($warehouse, $workspace);

        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            abort(422, 'Warehouses with active stock balances cannot be deleted.');
        }

        if (StockMovement::where('warehouse_id', $warehouse->id)->exists()) {
            abort(422, 'Warehouses with historical inventory movements cannot be deleted.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted successfully.');
    }

    private function workspace(Request $request, string $permission = 'inventory.warehouse.view'): Workspace
    {
        $workspace = Workspace::with('organization')->findOrFail($request->session()->get('active_workspace_id'));
        $user = $request->user();
        abort_unless($user, 401);

        abort_unless(
            $user->canInWorkspace($permission, $workspace) || $user->canInWorkspace('inventory.manage', $workspace),
            403,
            'Permission denied'
        );

        return $workspace;
    }

    private function assertWarehouse(Warehouse $warehouse, Workspace $workspace): void
    {
        abort_if((int) $warehouse->workspace_id !== (int) $workspace->id || (int) $warehouse->organization_id !== (int) $workspace->organization_id, 404);
    }
}
