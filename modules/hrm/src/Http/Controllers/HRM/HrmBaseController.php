<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

abstract class HrmBaseController extends Controller
{
    protected function isWorkspaceManager(Request $request, Workspace $workspace): bool
    {
        return $request->user()->canInWorkspace('hrm.manage', $workspace);
    }

    protected function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }

    protected function assertTenant($model, Workspace $workspace): void
    {
        abort_unless((int) $model->organization_id === (int) $workspace->organization_id && (int) $model->workspace_id === (int) $workspace->id, 404);
    }
}
