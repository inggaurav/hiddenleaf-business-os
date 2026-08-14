<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\BankTransferPayment;
use App\Models\Domain\SaaS\Order;
use App\Models\Domain\SaaS\Plan;
use App\Models\Domain\SaaS\Subscription;
use App\Models\HelpdeskTicket;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Inertia\Inertia;

class DashboardController
{
    public function index()
    {
        $totalOrders = Order::count();
        $totalPlans = Plan::count();
        $totalCompanies = Organization::count();
        $orderPayments = (float) Order::sum('price');

        $metrics = [
            // WorkDo reference metrics.
            'order_payments' => $orderPayments,
            'total_orders' => $totalOrders,
            'total_plans' => $totalPlans,
            'total_companies' => $totalCompanies,

            // HiddenLeaf extended SaaS metrics retained as a superset.
            'users' => User::count(),
            'organizations' => $totalCompanies,
            'workspaces' => Workspace::count(),
            'plans' => Plan::where('status', true)->count(),
            'orders' => $totalOrders,
            'paid_orders' => Order::whereIn('payment_status', ['paid', 'completed'])->count(),
            'revenue' => (float) Order::whereIn('payment_status', ['paid', 'completed'])->sum('price'),
            'active_subscriptions' => Subscription::where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'pending_bank_transfers' => BankTransferPayment::where('status', 'pending')->count(),
            'active_modules' => UserActiveModule::query()->count(),
            'open_helpdesk_tickets' => HelpdeskTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
        ];

        $chartData = $this->monthlyOrderChart();

        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics' => $metrics,
            'chartData' => $chartData,

            // Exact reference-friendly top-level aliases.
            'orderPayments' => $orderPayments,
            'totalOrders' => $totalOrders,
            'totalPlans' => $totalPlans,
            'totalCompanies' => $totalCompanies,

            // Backward-compatible aliases for the existing HiddenLeaf UI contract.
            'tenantsCount' => $metrics['workspaces'],
            'totalRevenue' => $metrics['revenue'],
            'activePlans' => $metrics['active_subscriptions'],
        ]);
    }

    private function monthlyOrderChart(): array
    {
        $year = now()->year;
        $orders = [];
        $payments = [];
        $labels = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
            $end = $start->endOfMonth();

            $query = Order::whereBetween('created_at', [$start, $end]);

            $labels[] = $start->format('M');
            $orders[] = (clone $query)->count();
            $payments[] = (float) (clone $query)->sum('price');
        }

        return [
            'labels' => $labels,
            'orders' => $orders,
            'payments' => $payments,
        ];
    }
}
