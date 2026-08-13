<?php

namespace App\Http\Controllers\Domain\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $orgId = session('active_organization_id');

        $subscription = null;
        if ($orgId) {
            $subscription = Subscription::with('plan')->where('organization_id', $orgId)->first();
        }

        return Inertia::render('Subscriptions/Index', [
            'subscription' => $subscription,
            'plans' => Plan::where('status', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'duration' => 'nullable|in:Month,Year,Lifetime,Trial',
            'coupon_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $plan = Plan::findOrFail($validated['plan_id']);
        $duration = $validated['duration'] ?? 'Month';

        $counter = [
            'user_counter' => $plan->number_of_users,
            'storage_limit' => $plan->storage_limit / (1024 * 1024),
        ];

        $result = assignPlan($plan->id, $duration, $plan->modules ?? [], $counter, $user->id);

        if (! $result['is_success']) {
            return back()->with('error', $result['error'] ?? 'Subscription failed.');
        }

        return redirect()->route('dashboard')->with('success', 'Subscription activated successfully.');
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        $user = Auth::user();
        $orgId = session('active_organization_id');

        if (! $user->isSuperAdmin() && $subscription->organization_id !== $orgId) {
            abort(403, 'Unauthorized.');
        }

        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Subscription cancelled.');
    }
}
