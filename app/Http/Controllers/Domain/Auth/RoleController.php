<?php

namespace App\Http\Controllers\Domain\Auth;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController
{
    public function index(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id');
        $roles = Role::whereNull('organization_id')
            ->orWhere('organization_id', $orgId)
            ->with('permissions')
            ->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::all(),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'organization_id' => $orgId,
            'name' => Str::slug($validated['name']),
            'display_name' => $validated['display_name'],
            'is_system' => false,
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request, $role);

        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request, $role);

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        $role->update(['display_name' => $validated['display_name']]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request, $role);

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $role->delete();
        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }

    protected function authorizeRoleAccess(Request $request, Role $role): void
    {
        $orgId = $request->session()->get('active_organization_id');

        // Prevent cross-organization role mutations
        if ($role->organization_id && (int)$role->organization_id !== (int)$orgId && !$request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized cross-organization role mutation.');
        }
    }
}
