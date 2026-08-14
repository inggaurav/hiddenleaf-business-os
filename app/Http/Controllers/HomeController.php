<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CrmLead;
use App\Models\HelpdeskTicket;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\BusinessRoleResolver;
use App\Services\DashboardDestinationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function Dashboard(
        Request $request,
        DashboardDestinationService $dashboardDestination,
        BusinessRoleResolver $businessRoleResolver
    ) {
        $user = $request->user();
        $orgId = $request->session()->get('active_organization_id');
        $wsId = $request->session()->get('active_workspace_id');

        // WorkDo parity: Super Admin owns a dedicated platform dashboard rather
        // than entering tenant navigation. Tenant impersonation/context remains
        // possible by explicitly selecting an organization/workspace first.
        if ($user->isSuperAdmin() && ! $orgId) {
            return redirect()->route('super-admin.dashboard');
        }

        $workspace = $wsId
            ? Workspace::with('organization')->whereKey($wsId)->first()
            : null;

        // WorkDo parity: /dashboard redirects a tenant actor to the first active
        // module dashboard they are allowed to use. `?overview=1` deliberately
        // preserves HiddenLeaf's richer executive overview as a secondary view.
        if ($workspace && ! $request->boolean('overview')) {
            $enabledModules = UserActiveModule::where('workspace_id', $workspace->id)
                ->pluck('module_name')
                ->map(static fn ($module) => strtolower((string) $module))
                ->values()
                ->all();

            if ($enabledModules === []) {
                $enabledModules = $request->session()->get('enabled_modules', []);
            }

            $destination = $dashboardDestination->firstPermittedRoute(
                $user,
                $workspace,
                is_array($enabledModules) ? $enabledModules : []
            );

            if ($destination) {
                return redirect()->route($destination);
            }
        }

        $organization = null;
        if ($user->isSuperAdmin() && ! $orgId) {
            $usersCount = User::count();
            $workspacesCount = Workspace::count();
            $ticketsCount = HelpdeskTicket::count();
            $recentLogs = AuditLog::with('actor')->latest()->take(5)->get();
        } else {
            // Strictly scoped to active tenant organization & workspace.
            $organization = $orgId ? Organization::find($orgId) : null;
            $usersCount = $organization ? $organization->members()->count() : 1;
            $workspacesCount = $orgId ? Workspace::where('organization_id', $orgId)->count() : 1;
            $ticketsCount = $wsId ? HelpdeskTicket::where('workspace_id', $wsId)->count() : 0;
            $recentLogs = $orgId
                ? AuditLog::where('organization_id', $orgId)->with('actor')->latest()->take(5)->get()
                : AuditLog::where('actor_id', $user->id)->with('actor')->latest()->take(5)->get();
        }

        return Inertia::render('Dashboard', [
            'businessRole' => $businessRoleResolver->resolve($user, $workspace),
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
            'recentLogs' => $recentLogs,
        ]);
    }
}
