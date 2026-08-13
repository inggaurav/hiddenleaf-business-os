<?php

namespace App\Http\Controllers\Domain\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        return Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
    }

    public function index(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (! $request->user()->canInWorkspace('roles.view', $workspace)) {
            abort(403, 'Unauthorized to view roles.');
        }

        $roles = Role::whereNull('organization_id')
            ->orWhere('organization_id', $workspace->organization_id)
            ->with('permissions')
            ->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::all(),
        ]);
    }

    public function create(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (! $request->user()->canInWorkspace('roles.create', $workspace)) {
            abort(403, 'Unauthorized to create roles.');
        }

        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);

        // 1. Authorize roles.create BEFORE mutation
        if (! $request->user()->canInWorkspace('roles.create', $workspace)) {
            abort(403, 'Unauthorized to create roles.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        // 2. Authorize roles.assign_permissions BEFORE mutation if permissions are supplied
        if (! empty($validated['permissions'])) {
            if (! $request->user()->canInWorkspace('roles.assign_permissions', $workspace)) {
                abort(403, 'Unauthorized to assign permissions to roles.');
            }
        }

        // 3. Execute atomic transaction
        DB::transaction(function () use ($workspace, $validated) {
            $role = Role::create([
                'organization_id' => $workspace->organization_id,
                'name' => Str::slug($validated['name']),
                'display_name' => $validated['display_name'],
                'is_system' => false,
            ]);

            if (! empty($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }
        });

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request, $role, 'roles.update');

        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $workspace = $this->getActiveWorkspace($request);

        // 1. System roles protection
        if ($role->is_system && ! $request->user()->isSuperAdmin()) {
            abort(403, 'System roles cannot be mutated by tenant administrators.');
        }

        // 2. Authorize roles.update BEFORE mutation
        $this->authorizeRoleAccess($request, $role, 'roles.update');

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        // 3. Authorize roles.assign_permissions BEFORE mutation if permissions are supplied
        if (isset($validated['permissions'])) {
            if (! $request->user()->canInWorkspace('roles.assign_permissions', $workspace)) {
                abort(403, 'Unauthorized to assign permissions to roles.');
            }
        }

        // 4. Execute atomic transaction
        DB::transaction(function () use ($role, $validated) {
            $role->update(['display_name' => $validated['display_name']]);

            if (isset($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }
        });

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $this->authorizeRoleAccess($request, $role, 'roles.delete');
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }

    protected function authorizeRoleAccess(Request $request, Role $role, string $permission): void
    {
        $workspace = $this->getActiveWorkspace($request);

        if (! $request->user()->canInWorkspace($permission, $workspace)) {
            abort(403, "Unauthorized role action: {$permission} required.");
        }

        if ($role->organization_id && (int) $role->organization_id !== (int) $workspace->organization_id && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized cross-organization role mutation.');
        }
    }
}
