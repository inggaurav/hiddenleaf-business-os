<?php

namespace App\Http\Controllers\Domain\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Services\AddonManager;
use App\Services\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly AddonManager $addonManager,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $plans = Plan::query()
            ->when(! $user->isSuperAdmin(), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('custom_plan', false)->orWhere('created_by', $user->id);
                });
            })
            ->withCount(['orders' => fn ($query) => $query->where('payment_status', 'succeeded')])
            ->latest()
            ->get();

        return Inertia::render('Plans/Index', [
            'plans' => $plans,
            'canCreate' => $user->isSuperAdmin(),
            'availableModules' => $this->availableModules(),
            'bankTransferEnabled' => (bool) admin_setting('bankTransferEnabled', true),
            'bankTransferInstructions' => admin_setting('bank_transfer_instructions', ''),
            'userTrialInfo' => ['is_trial_done' => $user->is_trial_done ?? 0],
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        return Inertia::render('Plans/Create', ['availableModules' => $this->availableModules()]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && $user->isSuperAdmin(), 403);
        $validated = $this->validatePlan($request, false);

        $plan = new Plan;
        $this->fillPlan($plan, $validated, $request);
        $plan->custom_plan = false;
        $plan->created_by = $user->id;
        $plan->save();

        return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
    }

    public function show(Plan $plan)
    {
        return redirect()->route('plans.edit', $plan);
    }

    public function edit(Plan $plan)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $planData = $plan->toArray();
        $planData['storage_limit'] = $plan->storage_limit ? round($plan->storage_limit / (1024 * 1024)) : 0;

        return Inertia::render('Plans/Edit', [
            'plan' => $planData,
            'availableModules' => $this->availableModules(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $validated = $this->validatePlan($request, true);
        $this->fillPlan($plan, $validated, $request);
        $plan->save();

        return redirect()->route('plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $inUse = Organization::where('plan_id', $plan->id)->exists() || User::where('active_plan', $plan->id)->exists();
        if ($inUse) {
            return back()->with('error', 'Cannot delete a plan that is assigned to an active customer.');
        }
        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan deleted successfully.');
    }

    public function subscribe(Plan $plan, Request $request)
    {
        $user = Auth::user();
        $workspaceId = $request->session()->get('active_workspace_id');

        return Inertia::render('Plans/Subscribe', [
            'plan' => $plan,
            'userActiveModules' => $workspaceId
                ? UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->toArray()
                : [],
            'bankTransferEnabled' => (bool) admin_setting('bankTransferEnabled', true),
            'bankTransferInstructions' => admin_setting('bank_transfer_instructions', ''),
            'planExpireDate' => $user->plan_expire_date,
        ]);
    }

    public function startTrial(Plan $plan)
    {
        $user = Auth::user();
        if (($user->is_trial_done ?? 0) >= 1) return back()->with('error', 'Trial has already been utilized for this account.');

        $counter = ['user_counter' => $plan->number_of_users, 'storage_limit' => $plan->storage_limit / (1024 * 1024)];
        $result = assignPlan($plan->id, 'Trial', $plan->modules ?? [], $counter, $user->id);

        return back()->with($result['is_success'] ? 'success' : 'error', $result['is_success'] ? 'Trial started successfully.' : ($result['error'] ?? 'Failed to start trial.'));
    }

    public function assignFreePlan(Request $request, Plan $plan)
    {
        $user = Auth::user();
        if (! $plan->free_plan) return back()->with('error', 'This plan is not a free plan.');

        $duration = $request->input('duration') === 'Year' ? 'Year' : 'Month';
        $counter = ['user_counter' => $plan->number_of_users, 'storage_limit' => $plan->storage_limit / (1024 * 1024)];
        $result = assignPlan($plan->id, $duration, $plan->modules ?? [], $counter, $user->id);

        if ($result['is_success']) {
            Order::create([
                'order_id' => strtoupper(substr(uniqid('FREE-'), -12)),
                'name' => $user->name,
                'email' => $user->email,
                'plan_name' => $plan->name,
                'plan_id' => $plan->id,
                'price' => 0,
                'currency' => 'USD',
                'payment_type' => 'free',
                'payment_status' => 'succeeded',
                'user_id' => $user->id,
                'created_by' => $user->id,
            ]);
            return back()->with('success', 'Free plan assigned successfully.');
        }

        return back()->with('error', $result['error'] ?? 'Failed to assign free plan.');
    }

    public function applyCoupon(Request $request)
    {
        $validated = $request->validate(['coupon_code' => 'required|string', 'total_amount' => 'required|numeric|min:0']);
        $result = applyCouponDiscount($validated['coupon_code'], (float) $validated['total_amount'], Auth::id());
        if (! $result['valid']) return response()->json(['success' => false, 'message' => $result['message']], 422);

        return response()->json([
            'success' => true,
            'discount_amount' => $result['discount_amount'],
            'final_amount' => $result['final_amount'],
            'coupon' => [
                'code' => $result['coupon']->code,
                'name' => $result['coupon']->name,
                'type' => $result['coupon']->type,
                'discount' => $result['coupon']->discount,
            ],
        ]);
    }

    private function validatePlan(Request $request, bool $updating): array
    {
        $required = $updating ? 'required' : 'nullable';
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'package_price_monthly' => "$required|numeric|min:0",
            'package_price_yearly' => "$required|numeric|min:0",
            'price_per_user_monthly' => 'nullable|numeric|min:0',
            'price_per_user_yearly' => 'nullable|numeric|min:0',
            'price_per_storage_monthly' => 'nullable|numeric|min:0',
            'price_per_storage_yearly' => 'nullable|numeric|min:0',
            'number_of_users' => "$required|integer|min:1",
            'storage_limit' => "$required|integer|min:0",
            'workspace_limit' => 'nullable|integer|min:1',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'trial' => 'nullable|boolean',
            'trial_days' => 'nullable|integer|min:0',
            'free_plan' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ]);
    }

    private function fillPlan(Plan $plan, array $validated, Request $request): void
    {
        $plan->name = $validated['name'];
        $plan->description = $validated['description'] ?? null;
        $plan->package_price_monthly = $validated['package_price_monthly'] ?? 0;
        $plan->package_price_yearly = $validated['package_price_yearly'] ?? 0;
        $plan->price_per_user_monthly = $validated['price_per_user_monthly'] ?? 0;
        $plan->price_per_user_yearly = $validated['price_per_user_yearly'] ?? 0;
        $plan->price_per_storage_monthly = $validated['price_per_storage_monthly'] ?? 0;
        $plan->price_per_storage_yearly = $validated['price_per_storage_yearly'] ?? 0;
        $plan->number_of_users = $validated['number_of_users'] ?? 1;
        $plan->storage_limit = ($validated['storage_limit'] ?? 0) * 1024 * 1024;
        $plan->workspace_limit = $validated['workspace_limit'] ?? 1;
        $plan->modules = array_values(array_unique(array_map('strtolower', $validated['modules'] ?? [])));
        $plan->trial = $request->boolean('trial', false);
        $plan->trial_days = $validated['trial_days'] ?? 0;
        $plan->free_plan = $request->boolean('free_plan', false);
        $plan->status = $request->boolean('status', true);
    }

    private function availableModules(): array
    {
        $rows = collect($this->moduleManager->getAllModules())->map(fn ($module) => [
            'name' => $module->getName(),
            'alias' => strtolower($module->getAlias()),
            'version' => $module->getVersion(),
            'type' => 'core',
        ]);

        foreach ($this->addonManager->installed() as $addon) {
            $alias = strtolower($addon->alias);
            if (! $rows->contains(fn ($row) => $row['alias'] === $alias)) {
                $rows->push(['name' => $addon->name, 'alias' => $alias, 'version' => $addon->version, 'type' => 'addon']);
            }
        }

        return $rows->sortBy('name')->values()->all();
    }
}
