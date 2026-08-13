<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        $warehouses = Warehouse::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function create(Request $request)
    {
        $this->workspace($request);

        return Inertia::render('Warehouses/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'city_zip' => 'nullable|string|max:20',
        ]);

        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        Warehouse::create([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'city_zip' => $validated['city_zip'] ?? null,
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
    }

    public function show(Request $request, Warehouse $warehouse)
    {
        $this->assertWarehouse($warehouse, $this->workspace($request));

        return redirect()->route('warehouses.edit', $warehouse);
    }

    public function edit(Request $request, Warehouse $warehouse)
    {
        $this->assertWarehouse($warehouse, $this->workspace($request));

        return Inertia::render('Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->assertWarehouse($warehouse, $this->workspace($request));
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'city_zip' => 'nullable|string|max:20',
        ]);

        $warehouse->update($validated);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        $this->assertWarehouse($warehouse, $this->workspace($request));
        abort_if($warehouse->stocks()->where('quantity', '>', 0)->exists(), 422, 'Warehouses with stock cannot be deleted.');
        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted successfully.');
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('inventory.manage', $workspace), 403);

        return $workspace;
    }

    private function assertWarehouse(Warehouse $warehouse, Workspace $workspace): void
    {
        abort_unless((int) $warehouse->organization_id === (int) $workspace->organization_id && (int) $warehouse->workspace_id === (int) $workspace->id, 404);
    }
}
