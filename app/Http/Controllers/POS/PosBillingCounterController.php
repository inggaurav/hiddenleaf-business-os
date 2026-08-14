<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\BillingCounter;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosBillingCounterController extends Controller
{
    public function index(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $counters = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest()
            ->get();

        $warehouses = Warehouse::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->get();

        return Inertia::render('POS/Counters/Index', [
            'counters' => $counters,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'counter_number' => 'required|string|max:100',
            'warehouse_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        BillingCounter::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => $validated['name'],
            'counter_number' => $validated['counter_number'],
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Billing counter created.');
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');

        $counter = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'counter_number' => 'sometimes|required|string|max:100',
            'warehouse_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $counter->update($validated);

        return redirect()->back()->with('success', 'Billing counter updated.');
    }

    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');

        $counter = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        // If counter has historical transactions, archive/inactivate or soft delete
        if ($counter->hasTransactions()) {
            $counter->update(['is_active' => false]);
            $counter->delete(); // soft delete

            return redirect()->back()->with('success', 'Billing counter archived because it has historical transactions.');
        }

        $counter->forceDelete();

        return redirect()->back()->with('success', 'Billing counter deleted.');
    }
}
