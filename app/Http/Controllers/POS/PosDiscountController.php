<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\PosDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosDiscountController extends Controller
{
    public function index(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $discounts = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest()
            ->paginate(15);

        return Inertia::render('POS/Discounts/Index', [
            'discounts' => $discounts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('POS/Discounts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
        ]);

        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        PosDiscount::create([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value' => (string) $validated['value'],
            'min_order_amount' => isset($validated['min_order_amount']) ? (string) $validated['min_order_amount'] : null,
            'max_discount_amount' => isset($validated['max_discount_amount']) ? (string) $validated['max_discount_amount'] : null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        return redirect()->route('pos.discounts.index')->with('success', 'Discount created successfully.');
    }

    public function show(Request $request, int|string $id): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $discount = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('POS/Discounts/Index', [
            'discount' => $discount,
        ]);
    }

    public function edit(Request $request, int|string $id): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $discount = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('POS/Discounts/Edit', [
            'discount' => $discount,
        ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');

        $discount = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $discount->update($validated);

        return redirect()->route('pos.discounts.index')->with('success', 'Discount updated successfully.');
    }

    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');

        $discount = PosDiscount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $discount->delete();

        return redirect()->route('pos.discounts.index')->with('success', 'Discount deleted successfully.');
    }
}
