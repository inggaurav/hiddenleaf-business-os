<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CrmLead;
use App\Models\CrmStage;
use App\Models\HelpdeskTicket;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function Dashboard(Request $request)
    {
        $user = $request->user();
        $orgId = $request->session()->get('active_organization_id');
        $wsId = $request->session()->get('active_workspace_id');

        if ($user->isSuperAdmin() && ! $orgId) {
            $usersCount = User::count();
            $workspacesCount = Workspace::count();
            $ticketsCount = HelpdeskTicket::count();
            $recentLogs = AuditLog::with('actor')->latest()->take(6)->get();
            $analytics = null;
        } else {
            // Strictly scoped to active tenant organization & workspace
            $organization = $orgId ? Organization::find($orgId) : null;
            $usersCount = $organization ? $organization->members()->count() : 1;
            $workspacesCount = $orgId ? Workspace::where('organization_id', $orgId)->count() : 1;
            $ticketsCount = $wsId ? HelpdeskTicket::where('workspace_id', $wsId)->count() : 0;
            $recentLogs = $orgId
                ? AuditLog::where('organization_id', $orgId)->with('actor')->latest()->take(6)->get()
                : AuditLog::where('actor_id', $user->id)->with('actor')->latest()->take(6)->get();

            // Generate monthly 6-month financial trajectory
            $monthlyIncome = [];
            $monthlyExpense = [];
            $monthLabels = [];

            for ($i = 5; $i >= 0; $i--) {
                $monthDate = Carbon::now()->subMonths($i);
                $monthKey = $monthDate->format('M');
                $monthLabels[] = $monthKey;

                $start = $monthDate->copy()->startOfMonth();
                $end = $monthDate->copy()->endOfMonth();

                $income = (float) SalesInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->whereNotIn('status', ['draft', 0])
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');

                $expense = (float) PurchaseInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->whereNotIn('status', ['draft', 0])
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');

                $monthlyIncome[] = $income;
                $monthlyExpense[] = $expense;
            }

            // Lead Pipeline distribution
            $pipelineDistribution = [
                ['stage' => 'Draft / Inbound', 'count' => CrmLead::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'open')->count()],
                ['stage' => 'Qualified', 'count' => CrmLead::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'qualified')->count()],
                ['stage' => 'Proposal Sent', 'count' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', ['sent', 'pending', 1])->count()],
                ['stage' => 'Won / Closed', 'count' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', ['paid', 3])->count()],
            ];

            // Task distribution
            $taskDistribution = [
                'pending' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNull('completed_at')->count(),
                'completed' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotNull('completed_at')->count(),
            ];

            // Workforce Attendance
            $totalEmployees = HrEmployee::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
            $todayPresent = HrAttendance::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereDate('attendance_date', today())->where('status', 'present')->count();

            $analytics = [
                'month_labels' => $monthLabels,
                'monthly_income' => $monthlyIncome,
                'monthly_expense' => $monthlyExpense,
                'pipeline' => $pipelineDistribution,
                'tasks' => $taskDistribution,
                'workforce' => [
                    'total_employees' => $totalEmployees,
                    'present_today' => $todayPresent,
                ],
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'users' => $usersCount,
                'workspaces' => $workspacesCount,
                'tickets' => $ticketsCount,
            ],
            'metrics' => $wsId ? [
                'members' => $usersCount,
                'workspaces' => $workspacesCount,
                'active_plan_name' => $organization?->plan_id ? Plan::whereKey($organization->plan_id)->value('name') : null,
                'products' => ProductServiceItem::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('type', 'product')->count(),
                'services' => ProductServiceItem::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('type', 'service')->count(),
                'sales' => (float) SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->sum('total_amount'),
                'paid_invoices' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))->count(),
                'purchases' => (float) PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->sum('total_amount'),
                'posted_purchases' => PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->count(),
                'open_tickets' => HelpdeskTicket::where('workspace_id', $wsId)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved_tickets' => HelpdeskTicket::where('workspace_id', $wsId)->whereIn('status', ['resolved', 'closed'])->count(),
                'active_projects' => TasklyProject::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'active')->count(),
                'open_tasks' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNull('completed_at')->count(),
                'open_leads' => CrmLead::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'open')->count(),
                'today_pos_sales' => (float) PosSale::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereDate('created_at', today())->where('status', 'completed')->sum('total'),
            ] : null,
            'analytics' => $analytics,
            'recentLogs' => $recentLogs,
        ]);
    }
}
