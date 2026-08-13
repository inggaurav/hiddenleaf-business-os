<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Domain\SaaS\Order;
use App\Models\Domain\SaaS\Subscription; // Assuming multi-tenant logic
use App\Models\MultiTenancy\Workspace;
use App\Models\Tenant;
use Inertia\Inertia;

class DashboardController
{
    public function index()
    {
        $tenantsCount = 0; // Or \App\Models\Workspace::count() depending on implementation
        if (class_exists(Workspace::class)) {
            $tenantsCount = Workspace::count();
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
