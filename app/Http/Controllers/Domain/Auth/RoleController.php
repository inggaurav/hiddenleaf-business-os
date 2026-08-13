<?php

namespace App\Http\Controllers\Domain\Auth;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController
{
    public function index(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id', 1);
        $roles = Role::whereNull('organization_id')
            ->orWhere('organization_id', $orgId)
            ->with('permissions')
            ->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $request->session()->get('active_organization_id', 1);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'organization_id' => $orgId,
            'name' => \Illuminate\Support\Str::slug($validated['name']),
            'display_name' => $validated['display_name'],
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return back()->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        $role->update(['display_name' => $validated['display_name']]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return back()->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $role->delete();
        return back()->with('success', 'Role deleted successfully.');
    }
}
