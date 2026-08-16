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
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController
{
    public function index()
    {
        $metrics = [
            'users' => User::count(),
            'organizations' => Organization::count(),
            'workspaces' => Workspace::count(),
            'plans' => Plan::where('status', true)->count(),
            'orders' => Order::count(),
            'paid_orders' => Order::whereIn('payment_status', ['paid', 'completed'])->count(),
            'revenue' => (float) Order::whereIn('payment_status', ['paid', 'completed'])->sum('price'),
            'active_subscriptions' => Subscription::where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'pending_bank_transfers' => BankTransferPayment::where('status', 'pending')->count(),
            'active_modules' => UserActiveModule::query()->count(),
            'open_helpdesk_tickets' => HelpdeskTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
        ];
        $monthlyOrders = collect(range(5, 0))->map(function (int $offset) {
            $month = Carbon::now()->subMonths($offset);
            $query = Order::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month);

            return ['month' => $month->format('M'), 'orders' => (clone $query)->count(), 'revenue' => (float) (clone $query)->whereIn('payment_status', ['paid', 'completed'])->sum('price')];
        });
        $ticketStatus = HelpdeskTicket::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $weeklyPending = collect(range(6, 0))->map(function (int $offset) {
            $date = Carbon::now()->subDays($offset);

            return ['day' => $date->format('D'), 'tickets' => HelpdeskTicket::whereDate('created_at', $date)->whereNotIn('status', ['resolved', 'closed'])->count()];
        });

        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics' => $metrics,
            // Backward-compatible aliases for the frozen UI contract.
            'tenantsCount' => $metrics['workspaces'],
            'totalRevenue' => $metrics['revenue'],
            'activePlans' => $metrics['active_subscriptions'],
            'monthlyOrders' => $monthlyOrders,
            'ticketStatus' => $ticketStatus,
            'weeklyPendingTickets' => $weeklyPending,
            'recentTickets' => HelpdeskTicket::with(['category', 'creator'])->latest()->limit(8)->get(),
        ]);
    }
}
