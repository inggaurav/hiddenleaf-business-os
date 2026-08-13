<?php

namespace HiddenLeaf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use HiddenLeaf\Architecture\OrganizationContext;
use HiddenLeaf\Architecture\WorkspaceContext;

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
        $orgId = (int) ($request->header('X-Organization-ID') ?? $request->session()->get('active_organization_id', 1));
        $wsId = (int) ($request->header('X-Workspace-ID') ?? $request->session()->get('active_workspace_id', 1));
        $wsTitle = (string) $request->session()->get('active_workspace_title', 'Main Operations');

        $this->orgContext->set($orgId, 'HiddenLeaf Organization');
        $this->workspaceContext->set($wsId, $orgId, $wsTitle);

        return $next($request);
    }
}
