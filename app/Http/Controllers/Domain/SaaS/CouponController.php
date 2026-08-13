<?php

namespace App\Http\Controllers\Domain\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $coupons = Coupon::query()
            ->when($request->name, fn ($q) => $q->where('name', 'like', "%{$request->name}%"))
            ->when($request->code, fn ($q) => $q->where('code', 'like', "%{$request->code}%"))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->status !== null, fn ($q) => $q->where('status', $request->boolean('status')))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Coupons/Index', [
            'coupons' => $coupons,
            'canCreate' => $user->isSuperAdmin(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Coupons/Create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            return redirect()->route('coupons.index')->with('error', 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'required|string|max:50|unique:coupons,code',
            'discount' => 'required|numeric|min:0',
            'type' => 'required|in:percentage,fixed,flat',
            'limit' => 'nullable|integer|min:1',
            'limit_per_user' => 'nullable|integer|min:1',
            'minimum_spend' => 'nullable|numeric|min:0',
            'maximum_spend' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|boolean',
        ]);

        $type = in_array($validated['type'], ['fixed', 'flat']) ? 'fixed' : 'percentage';

        Coupon::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'code' => strtoupper($validated['code']),
            'discount' => $validated['discount'],
            'type' => $type,
            'limit' => $validated['limit'] ?? null,
            'limit_per_user' => $validated['limit_per_user'] ?? null,
            'minimum_spend' => $validated['minimum_spend'] ?? null,
            'maximum_spend' => $validated['maximum_spend'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'status' => $request->boolean('status', true),
            'created_by' => $user->id,
        ]);

        return redirect()->route('coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function show(Coupon $coupon, Request $request)
    {
        $usageRecords = UserCoupon::with(['user:id,name,email'])
            ->where('coupon_id', $coupon->id)
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Coupons/Show', [
            'coupon' => $coupon,
            'usageRecords' => $usageRecords,
        ]);
    }

    public function edit(Coupon $coupon)
    {
        return Inertia::render('Coupons/Edit', [
            'coupon' => $coupon,
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            return redirect()->route('coupons.index')->with('error', 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'required|string|max:50|unique:coupons,code,'.$coupon->id,
            'discount' => 'required|numeric|min:0',
            'type' => 'required|in:percentage,fixed,flat',
            'limit' => 'nullable|integer|min:1',
            'limit_per_user' => 'nullable|integer|min:1',
            'minimum_spend' => 'nullable|numeric|min:0',
            'maximum_spend' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|boolean',
        ]);

        $type = in_array($validated['type'], ['fixed', 'flat']) ? 'fixed' : 'percentage';

        $coupon->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'code' => strtoupper($validated['code']),
            'discount' => $validated['discount'],
            'type' => $type,
            'limit' => $validated['limit'] ?? null,
            'limit_per_user' => $validated['limit_per_user'] ?? null,
            'minimum_spend' => $validated['minimum_spend'] ?? null,
            'maximum_spend' => $validated['maximum_spend'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            return redirect()->route('coupons.index')->with('error', 'Unauthorized');
        }

        $coupon->delete();

        return redirect()->route('coupons.index')->with('success', 'Coupon deleted successfully.');
    }
}
