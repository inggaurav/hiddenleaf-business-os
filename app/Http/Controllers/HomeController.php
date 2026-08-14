<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HelpdeskTicket;
use App\Models\Organization;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
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
            $recentLogs = AuditLog::with('actor')->latest()->take(5)->get();
        } else {
            // Strictly scoped to active tenant organization & workspace
            $organization = $orgId ? Organization::find($orgId) : null;
            $usersCount = $organization ? $organization->members()->count() : 1;
            $workspacesCount = $orgId ? Workspace::where('organization_id', $orgId)->count() : 1;
            $ticketsCount = $wsId ? HelpdeskTicket::where('workspace_id', $wsId)->count() : 0;
            $recentLogs = $orgId
                ? AuditLog::where('organization_id', $orgId)->with('actor')->latest()->take(5)->get()
                : AuditLog::where('actor_id', $user->id)->with('actor')->latest()->take(5)->get();
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'users' => $usersCount,
                'workspaces' => $workspacesCount,
                'tickets' => $ticketsCount,
            ],
            'metrics' => $wsId ? [
                'members' => $usersCount,
                'products' => ProductServiceItem::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('type', 'product')->count(),
                'services' => ProductServiceItem::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('type', 'service')->count(),
                'sales' => (float) SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereIn('status', [1, 2, 3])->sum('total_amount'),
                'purchases' => (float) PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 1)->sum('total_amount'),
                'open_tickets' => HelpdeskTicket::where('organization_id', $orgId)->where('workspace_id', $wsId)->whereNotIn('status', ['resolved', 'closed'])->count(),
            ] : null,
            'recentLogs' => $recentLogs,
        ]);
    }
}
