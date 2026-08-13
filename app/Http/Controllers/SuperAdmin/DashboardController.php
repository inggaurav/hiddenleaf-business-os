<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Tenant; // Assuming multi-tenant logic
use App\Models\Domain\SaaS\Order;
use App\Models\Domain\SaaS\Plan;
use App\Models\Domain\SaaS\Subscription;

class DashboardController
{
    public function index()
    {
        $tenantsCount = 0; // Or \App\Models\Workspace::count() depending on implementation
        if (class_exists(\App\Models\MultiTenancy\Workspace::class)) {
            $tenantsCount = \App\Models\MultiTenancy\Workspace::count();
        }

        $totalRevenue = Order::where('payment_status', 'completed')->sum('price');
        
        $activePlans = Subscription::where('status', 'active')->count();

        return Inertia::render('SuperAdmin/Dashboard', [
            'tenantsCount' => $tenantsCount,
            'totalRevenue' => $totalRevenue,
            'activePlans' => $activePlans,
        ]);
    }
}
