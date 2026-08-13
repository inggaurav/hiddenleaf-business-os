<?php

namespace App\Http\Controllers\Domain\SaaS;

use App\Models\Domain\SaaS\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController
{
    public function index()
    {
        return Inertia::render('SaaS/Plans/Index', [
            'plans' => Plan::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('SaaS/Plans/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'price_monthly' => 'required|numeric',
            'price_yearly' => 'required|numeric',
            'max_users' => 'required|integer',
            'max_storage' => 'required|integer',
            'modules' => 'nullable|array',
            'trial_days' => 'required|integer',
        ]);

        Plan::create($validated);

        return redirect()->route('plans.index');
    }

    public function show(Plan $plan)
    {
        return Inertia::render('SaaS/Plans/Show', ['plan' => $plan]);
    }

    public function edit(Plan $plan)
    {
        return Inertia::render('SaaS/Plans/Edit', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'price_monthly' => 'required|numeric',
            'price_yearly' => 'required|numeric',
            'max_users' => 'required|integer',
            'max_storage' => 'required|integer',
            'modules' => 'nullable|array',
            'trial_days' => 'required|integer',
        ]);

        $plan->update($validated);

        return redirect()->route('plans.index');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return redirect()->route('plans.index');
    }
}
