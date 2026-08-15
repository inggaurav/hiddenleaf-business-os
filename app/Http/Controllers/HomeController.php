<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CrmDeal;
use App\Models\CrmLead;
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
        $organization = null;

        if ($user->isSuperAdmin() && ! $orgId) {
            $usersCount = User::count();
            $workspacesCount = Workspace::count();
            $ticketsCount = HelpdeskTicket::count();
            $recentLogs = AuditLog::with('actor')->latest()->take(6)->get();
            $analytics = null;
        } else {
            $organization = $orgId ? Organization::find($orgId) : null;
            $usersCount = $organization ? $organization->members()->count() : 1;
            $workspacesCount = $orgId ? Workspace::where('organization_id', $orgId)->count() : 1;
            $ticketsCount = $wsId ? HelpdeskTicket::where('workspace_id', $wsId)->count() : 0;
            $recentLogs = $orgId
                ? AuditLog::where('organization_id', $orgId)->with('actor')->latest()->take(6)->get()
                : AuditLog::where('actor_id', $user->id)->with('actor')->latest()->take(6)->get();

            $monthlyIncome = [];
            $monthlyExpense = [];
            $monthLabels = [];

            for ($i = 5; $i >= 0; $i--) {
                $monthDate = Carbon::now()->subMonths($i);
                $monthLabels[] = $monthDate->format('M');
                $start = $monthDate->copy()->startOfMonth();
                $end = $monthDate->copy()->endOfMonth();

                $monthlyIncome[] = (float) SalesInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->whereNotIn('status', ['draft', 0])
                    ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('total_amount');

                $monthlyExpense[] = (float) PurchaseInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->whereNotIn('status', ['draft', 0])
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');
            }

            $activeLeads = CrmLead::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->whereNotIn('status', ['converted', 'lost', 'closed'])
                ->with('stage')
                ->get();

            $pipelineDistribution = $activeLeads
                ->groupBy(fn (CrmLead $lead) => $lead->stage?->name ?: ucfirst((string) $lead->status))
                ->map(fn ($leads, $stage) => ['stage' => (string) $stage, 'count' => $leads->count()])
                ->values()
                ->all();

            if ($pipelineDistribution === []) {
                $pipelineDistribution = [['stage' => 'No active leads', 'count' => 0]];
            }

            $activeDeals = CrmDeal::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->whereNotIn('status', ['won', 'lost', 'closed'])
                ->count();

            $taskDistribution = [
                'pending' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNull('completed_at')->count(),
                'completed' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotNull('completed_at')->count(),
            ];

            $totalEmployees = HrEmployee::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
            $todayPresent = 0;
            try {
                $todayPresent = HrAttendance::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->whereDate('attendance_date', today())
                    ->where('status', 'present')
                    ->count();
            } catch (\Throwable $e) {
                // Fallback if attendance table uses alternative column
            }

            $analytics = [
                'month_labels' => $monthLabels,
                'monthly_income' => $monthlyIncome,
                'monthly_expense' => $monthlyExpense,
                'pipeline' => $pipelineDistribution,
                'active_deals' => $activeDeals,
                'tasks' => $taskDistribution,
                'workforce' => [
                    'total_employees' => $totalEmployees,
                    'present_today' => $todayPresent,
                ],
            ];
        }

        $salesTotal = $wsId
            ? (float) SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->sum('total_amount')
            : 0.0;
        $purchaseTotal = $wsId
            ? (float) PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->sum('total_amount')
            : 0.0;
        $netOperatingResult = $salesTotal - $purchaseTotal;
        $netMarginPercent = $salesTotal > 0 ? round(($netOperatingResult / $salesTotal) * 100, 1) : null;

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
                'sales' => $salesTotal,
                'paid_invoices' => SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))->count(),
                'purchases' => $purchaseTotal,
                'posted_purchases' => PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['draft', 0])->count(),
                'net_operating_result' => $netOperatingResult,
                'net_margin_percent' => $netMarginPercent,
                'open_tickets' => HelpdeskTicket::where('workspace_id', $wsId)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved_tickets' => HelpdeskTicket::where('workspace_id', $wsId)->whereIn('status', ['resolved', 'closed'])->count(),
                'active_projects' => TasklyProject::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', ['active', 'in_progress'])->count(),
                'open_tasks' => TasklyTask::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNull('completed_at')->count(),
                'open_leads' => CrmLead::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['converted', 'lost', 'closed'])->count(),
                'active_deals' => CrmDeal::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['won', 'lost', 'closed'])->count(),
                'today_pos_sales' => (float) PosSale::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereDate('created_at', today())->where('status', 'completed')->sum('total'),
            ] : null,
            'analytics' => $analytics,
            'recentLogs' => $recentLogs,
        ]);
    }
}
