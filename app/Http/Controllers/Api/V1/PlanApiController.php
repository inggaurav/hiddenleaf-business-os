<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;

class PlanApiController extends Controller
{
    public function index()
    {
        $plans = Plan::where('status', true)->where('custom_plan', false)->get();

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }

    public function show(Plan $plan)
    {
        return response()->json([
            'success' => true,
            'plan' => $plan,
        ]);
    }
}
