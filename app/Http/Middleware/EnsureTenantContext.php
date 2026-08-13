<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Workspace;
use Closure;
use HiddenLeaf\Architecture\OrganizationContext;
use HiddenLeaf\Architecture\WorkspaceContext;
use Illuminate\Http\Request;

class EnsureTenantContext
{
    protected OrganizationContext $orgContext;

    protected WorkspaceContext $workspaceContext;

    public function __construct(OrganizationContext $orgContext, WorkspaceContext $workspaceContext)
    {
        $this->orgContext = $orgContext;
        $this->workspaceContext = $workspaceContext;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Guest routes do not enforce tenant context
        if (! $user) {
            return $next($request);
        }

        // Super Admin bypasses membership restriction if accessing admin panel
        if ($user->isSuperAdmin() && $request->is('admin*')) {
            return $next($request);
        }

        // Resolve requested Organization ID (header overrides session, but MUST be verified)
        $requestedOrgId = $request->header('X-Organization-ID')
            ? (int) $request->header('X-Organization-ID')
            : $request->session()->get('active_organization_id');

        // Resolve requested Workspace ID
        $requestedWsId = $request->header('X-Workspace-ID')
            ? (int) $request->header('X-Workspace-ID')
            : $request->session()->get('active_workspace_id');

        // If no organization requested, resolve user's primary/first organization
        if (! $requestedOrgId) {
            $userOrg = $user->organizations()->first();
            if ($userOrg) {
                $requestedOrgId = $userOrg->id;
            }
        }

        if (! $requestedOrgId) {
            // User belongs to no organization
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No active organization context found.'], 403);
            }

            return redirect('/login')->with('error', 'Please join or create an organization.');
        }

        // VERIFY: Authenticated user must belong to requested organization (or be super admin)
        $isOrgMember = $user->isSuperAdmin() || $user->organizations()->where('organizations.id', $requestedOrgId)->exists();
        if (! $isOrgMember) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized organization access.'], 403);
            }
            abort(403, 'Unauthorized organization access.');
        }

        $organization = Organization::find($requestedOrgId);
        if (! $organization || ! $organization->is_active) {
            abort(403, 'Organization is inactive or suspended.');
        }

        // Resolve Workspace within the verified Organization
        if (! $requestedWsId) {
            $userWs = $user->workspaces()->where('workspaces.organization_id', $organization->id)->first();
            $requestedWsId = $userWs ? $userWs->id : optional($organization->workspaces()->first())->id;
        }

        if ($requestedWsId) {
            $workspace = Workspace::find($requestedWsId);

            // VERIFY: Workspace MUST belong to selected Organization
            if (! $workspace || (int) $workspace->organization_id !== (int) $organization->id) {
                abort(403, 'Workspace does not belong to the selected organization.');
            }

            // VERIFY: Authenticated user MUST belong to Workspace (or be org owner/super admin)
            $isWsMember = $user->isSuperAdmin()
                || (int) $organization->owner_id === (int) $user->id
                || $user->workspaces()->where('workspaces.id', $workspace->id)->exists();

            if (! $isWsMember) {
                abort(403, 'Unauthorized workspace access.');
            }

            $this->workspaceContext->set($workspace->id, $organization->id, $workspace->name);
            $request->session()->put('active_workspace_id', $workspace->id);
            $request->session()->put('active_workspace_title', $workspace->name);
        }

        $this->orgContext->set($organization->id, $organization->name);
        $request->session()->put('active_organization_id', $organization->id);

        return $next($request);
    }
}
