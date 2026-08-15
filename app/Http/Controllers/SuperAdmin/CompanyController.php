<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $companies = Organization::query()
            ->with(['owner:id,name,email', 'plan:id,name'])
            ->withCount(['workspaces', 'members'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
            'plans' => Plan::where('status', true)->orderBy('name')->get(['id', 'name', 'trial', 'trial_days']),
            'filters' => ['search' => $search],
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($organization, $data) {
            $planId = $data['plan_id'] ?? null;
            $plan = $planId ? Plan::findOrFail($planId) : null;

            $organization->forceFill([
                'plan_id' => $plan?->id,
                'is_active' => $data['is_active'],
                'plan_expires_at' => $plan && $plan->trial
                    ? now()->addDays(max(1, (int) $plan->trial_days))
                    : $organization->plan_expires_at,
            ])->save();

            Subscription::where('organization_id', $organization->id)->update(['status' => 'inactive']);

            if ($plan) {
                Subscription::updateOrCreate(
                    ['organization_id' => $organization->id, 'plan_id' => $plan->id],
                    [
                        'status' => $data['is_active'] ? 'active' : 'inactive',
                        'starts_at' => now(),
                        'expires_at' => $plan->trial ? now()->addDays(max(1, (int) $plan->trial_days)) : null,
                    ]
                );
            }
        });

        return back()->with('success', 'Company access and plan updated. Workspace modules remain data-safe and are filtered by the new plan entitlement.');
    }
}
