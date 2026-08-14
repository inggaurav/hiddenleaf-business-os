<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\BusinessRoleResolver;
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
        $activeWorkspace = null;

        if ($user && $request->session()->get('active_workspace_id')) {
            $activeWorkspace = Workspace::with('organization')
                ->find($request->session()->get('active_workspace_id'));
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'business_role' => app(BusinessRoleResolver::class)->resolve($user, $activeWorkspace),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'permissions' => $this->resolvePermissions($request, $user),
                ] : null,
                'notifications' => $user ? $this->resolveNotifications($request, $user) : [],
            ],
            'tenant' => [
                'organization_id' => $request->session()->get('active_organization_id'),
                'workspace_id' => $request->session()->get('active_workspace_id'),
                'workspace_title' => $request->session()->get('active_workspace_title'),
                'available_workspaces' => $user ? $this->resolveWorkspaces($request, $user) : [],
                'modules' => $this->resolveModules($request, $user),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    protected function resolveNotifications(Request $request, $user): array
    {
        $workspaceId = $request->session()->get('active_workspace_id');

        return $user->notifications()
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($n) {
                $data = is_array($n->data) ? $n->data : (json_decode($n->data ?? '{}', true) ?: []);
                $title = $data['title'] ?? ($data['subject'] ?? ($data['name'] ?? 'System Alert'));
                $message = $data['message'] ?? ($data['body'] ?? ($data['description'] ?? ''));

                return [
                    'id' => (string) $n->id,
                    'type' => $n->type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'read_at' => $n->read_at ? $n->read_at->toISOString() : null,
                    'created_at' => $n->created_at ? $n->created_at->diffForHumans() : '',
                ];
            })
            ->toArray();
    }

    protected function resolveModules(Request $request, $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return ['account', 'productservice', 'hrm', 'lead', 'taskly', 'pos', 'landingpage', 'core', 'sales', 'procurement'];
        }

        $sessionModules = $request->session()->get('enabled_modules');
        if (is_array($sessionModules) && ! empty($sessionModules)) {
            return $sessionModules;
        }

        $orgId = $request->session()->get('active_organization_id');
        if ($orgId) {
            $subscription = Subscription::where('organization_id', $orgId)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($subscription && $subscription->plan && ! empty($subscription->plan->modules)) {
                $planModules = is_string($subscription->plan->modules)
                    ? json_decode($subscription->plan->modules, true)
                    : $subscription->plan->modules;

                if (is_array($planModules) && ! empty($planModules)) {
                    return $planModules;
                }
            }
        }

        return ['productservice', 'sales', 'procurement', 'core'];
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
