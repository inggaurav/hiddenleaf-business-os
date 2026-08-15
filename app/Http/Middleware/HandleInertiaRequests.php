<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\UserActiveModule;
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
        $isOwner = $user ? $this->isActiveOrganizationOwner($request, $user) : false;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_owner' => $isOwner,
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
            return [
                'account', 'productservice', 'hrm', 'lead', 'crm', 'taskly', 'pos', 'landingpage',
                'core', 'sales', 'procurement', 'communications', 'automations', 'missions',
                'command_center', 'knowledge', 'helpdesk', 'media',
            ];
        }

        $orgId = (int) $request->session()->get('active_organization_id');
        $workspaceId = (int) $request->session()->get('active_workspace_id');
        $modules = [];

        if ($orgId > 0) {
            $subscription = Subscription::where('organization_id', $orgId)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($subscription?->plan && ! empty($subscription->plan->modules)) {
                $modules = array_merge($modules, (array) $subscription->plan->modules);
            }

            $organization = Organization::find($orgId);
            if ($organization?->plan_id) {
                $planModules = Plan::whereKey($organization->plan_id)->value('modules');
                if (is_string($planModules)) {
                    $planModules = json_decode($planModules, true) ?: [];
                }
                if (is_array($planModules)) {
                    $modules = array_merge($modules, $planModules);
                }
            }
        }

        if ($workspaceId > 0) {
            $modules = array_merge(
                $modules,
                UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->all()
            );
        }

        $sessionModules = $request->session()->get('enabled_modules');
        if (is_array($sessionModules)) {
            $modules = array_merge($modules, $sessionModules);
        }

        if ($modules === []) {
            $modules = ['productservice', 'sales', 'procurement', 'core'];
        }

        $modules = array_values(array_unique(array_map(
            static fn ($module): string => strtolower(trim((string) $module)),
            array_filter($modules)
        )));

        if (in_array('crm', $modules, true) && ! in_array('lead', $modules, true)) {
            $modules[] = 'lead';
        }

        return $modules;
    }

    protected function resolvePermissions(Request $request, $user): array
    {
        if (
            $user->isSuperAdmin()
            || $user->role === 'company_admin'
            || $user->role === 'company'
            || $this->isActiveOrganizationOwner($request, $user)
        ) {
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

        $orgId = (int) $request->session()->get('active_organization_id');
        if ($orgId > 0 && (
            $user->role === 'company_admin'
            || $user->role === 'company'
            || $this->isActiveOrganizationOwner($request, $user)
        )) {
            return Workspace::where('organization_id', $orgId)->select('id', 'name')->get()->toArray();
        }

        return $user->workspaces()->select('workspaces.id', 'workspaces.name')->get()->toArray();
    }

    private function isActiveOrganizationOwner(Request $request, $user): bool
    {
        $orgId = (int) $request->session()->get('active_organization_id');
        if ($orgId <= 0) {
            return false;
        }

        return Organization::whereKey($orgId)->where('owner_id', $user->id)->exists();
    }
}
