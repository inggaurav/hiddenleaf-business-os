<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HelpdeskTicket;
use App\Models\Organization;
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
            'recentLogs' => $recentLogs,
        ]);
    }
}
