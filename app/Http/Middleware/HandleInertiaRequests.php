<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'is_super_admin' => $user->isSuperAdmin(),
                    'permissions' => $this->resolvePermissions($request, $user),
                ] : null,
                'notifications' => $user ? [] : [],
            ],
            'tenant' => [
                'organization_id' => $request->session()->get('active_organization_id'),
                'workspace_id' => $request->session()->get('active_workspace_id'),
                'workspace_title' => $request->session()->get('active_workspace_title'),
                'available_workspaces' => $user ? $this->resolveWorkspaces($request, $user) : [],
                'modules' => $request->session()->get('enabled_modules', []),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    protected function resolvePermissions(Request $request, $user): array
    {
        if ($user->isSuperAdmin() || $user->role === 'company_admin' || $user->role === 'company') {
            return Permission::pluck('name')->toArray();
        }

        $workspaceId = $request->session()->get('active_workspace_id');
        if (! $workspaceId) {
            return [];
        }

        $membership = $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        if (! $membership || ! $membership->pivot->role_id) {
            return [];
        }

        $role = Role::find($membership->pivot->role_id);
        if (! $role) {
            return [];
        }

        return $role->permissions()->pluck('name')->toArray();
    }

    protected function resolveWorkspaces(Request $request, $user): array
    {
        if ($user->isSuperAdmin()) {
            return Workspace::select('id', 'name')->limit(50)->get()->toArray();
        }

        $orgId = $request->session()->get('active_organization_id');
        if ($orgId && ($user->role === 'company_admin' || $user->role === 'company')) {
            return Workspace::where('organization_id', $orgId)->select('id', 'name')->get()->toArray();
        }

        return $user->workspaces()->select('workspaces.id', 'workspaces.name')->get()->toArray();
    }
}
