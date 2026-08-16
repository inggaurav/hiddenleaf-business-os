<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginDetail;
use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $actor = Auth::user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');
        $roles = $this->rolesForOrganization($orgId);
        $roleNames = $roles->pluck('display_name', 'id');

        $users = User::query()
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->whereHas('organizations', fn ($query) => $query->where('organizations.id', $orgId)))
            ->when($request->search, function ($q, $search) {
                $q->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->withQueryString()
            ->through(function (User $user) use ($wsId, $roleNames) {
                $roleId = $wsId ? $user->workspaces()->where('workspaces.id', $wsId)->first()?->pivot?->role_id : null;
                $row = $user->toArray();
                $row['workspace_role_id'] = $roleId;
                $row['workspace_role'] = $roleId ? ($roleNames[$roleId] ?? 'Custom Role') : null;

                return $row;
            });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'plans' => Plan::where('status', true)->orderBy('name')->get(),
            'isSuperAdmin' => $actor->isSuperAdmin(),
        ]);
    }

    public function create(Request $request)
    {
        $actor = Auth::user();
        $orgId = $request->session()->get('active_organization_id');

        return Inertia::render('Users/Create', [
            'roles' => $this->rolesForOrganization($orgId),
            'plans' => $actor->isSuperAdmin() ? Plan::where('status', true)->orderBy('name')->get() : [],
            'isSuperAdmin' => $actor->isSuperAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $actor = Auth::user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');
        $workspace = $wsId ? Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail() : null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ]);

        $role = $this->roleForOrganization((int) $validated['role_id'], $orgId);
        $globalRole = in_array($role->name, ['company', 'company_admin', 'client', 'vendor'], true) ? $role->name : 'user';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $globalRole,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($orgId) {
            $user->organizations()->syncWithoutDetaching([$orgId => ['role' => $role->name]]);
        }
        if ($workspace) {
            $user->workspaces()->syncWithoutDetaching([$workspace->id => ['role_id' => $role->id]]);
            $this->syncPortalParty($user, $workspace, $globalRole, $actor->id);
        }

        if ($actor->isSuperAdmin() && ! empty($validated['plan_id'])) {
            $plan = Plan::findOrFail($validated['plan_id']);
            assignPlan($plan->id, 'Month', $plan->modules ?? [], [], $user->id);
        }

        return redirect()->route('users.index')->with('success', 'User created and workspace role assigned.');
    }

    public function show(User $user)
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(Request $request, User $user)
    {
        $this->assertUserVisibleToActor($request, $user);
        $orgId = $request->session()->get('active_organization_id');
        $wsId = $request->session()->get('active_workspace_id');
        $membership = $wsId ? $user->workspaces()->where('workspaces.id', $wsId)->first() : null;
        $data = $user->toArray();
        $data['role_id'] = $membership?->pivot?->role_id;

        return Inertia::render('Users/Edit', [
            'user' => $data,
            'roles' => $this->rolesForOrganization($orgId),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->assertUserVisibleToActor($request, $user);
        $orgId = $request->session()->get('active_organization_id');
        $wsId = $request->session()->get('active_workspace_id');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = $this->roleForOrganization((int) $validated['role_id'], $orgId);
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => in_array($role->name, ['company', 'company_admin', 'client', 'vendor'], true) ? $role->name : 'user',
        ]);

        if ($orgId) {
            $user->organizations()->syncWithoutDetaching([$orgId => ['role' => $role->name]]);
        }
        if ($wsId) {
            $user->workspaces()->syncWithoutDetaching([$wsId => ['role_id' => $role->id]]);
            $workspace = Workspace::whereKey($wsId)->where('organization_id', $orgId)->firstOrFail();
            $this->syncPortalParty($user, $workspace, $user->role, $request->user()->id);
        }

        return redirect()->route('users.index')->with('success', 'User and workspace role updated.');
    }

    private function syncPortalParty(User $user, Workspace $workspace, string $role, int $actorId): void
    {
        if ($role === 'client') {
            AccountCustomer::updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['organization_id' => $workspace->organization_id, 'name' => $user->name, 'email' => $user->email, 'is_active' => true, 'created_by' => $actorId]
            );
        }
        if ($role === 'vendor') {
            AccountVendor::updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['organization_id' => $workspace->organization_id, 'name' => $user->name, 'email' => $user->email, 'is_active' => true, 'created_by' => $actorId]
            );
        }
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        $this->assertUserVisibleToActor($request, $user);
        if ($user->id === $actor->id) {
            return back()->with('error', 'Cannot delete your own account.');
        }
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin accounts cannot be deleted.');
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function assignPlan(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $validated = $request->validate(['plan_id' => 'required|exists:plans,id', 'duration' => 'nullable|in:Month,Year,Lifetime']);
        $plan = Plan::findOrFail($validated['plan_id']);
        assignPlan($plan->id, $validated['duration'] ?? 'Month', $plan->modules ?? [], [], $user->id);

        return back()->with('success', "Plan {$plan->name} assigned to {$user->name}.");
    }

    public function loginHistory(Request $request)
    {
        $actor = $request->user();
        $logs = LoginDetail::with('user')
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('user_id', $actor->id))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Users/LoginHistory', ['logs' => $logs]);
    }

    public function changePassword(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if ((int) $actor->id !== (int) $user->id && ! $actor->isSuperAdmin()) {
            $this->assertUserVisibleToActor($request, $user);
            $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
            abort_unless($actor->canInWorkspace('users.change_password', $workspace), 403);
        }

        $request->validate(['password' => 'required|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated for '.$user->name);
    }

    public function toggleStatus(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if (! $actor->isSuperAdmin()) {
            $this->assertUserVisibleToActor($request, $user);
            $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
            abort_unless($actor->canInWorkspace('users.toggle_status', $workspace), 403);
        }
        if ((int) $actor->id === (int) $user->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin accounts cannot be deactivated.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'User account status updated.');
    }

    public function impersonate(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($actor->isSuperAdmin(), 403);
        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Nested impersonation is prohibited.');
        }
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Another Super Admin cannot be impersonated.');
        }

        $request->session()->put('impersonator_id', $actor->id);
        $this->auditLogger->log($actor->id, $request->session()->get('active_organization_id'), $request->session()->get('active_workspace_id'), 'impersonation.start', 'user', (string) $user->id, ['target_email' => $user->email], $request->ip(), $request->userAgent(), true);
        auth()->login($user);

        return redirect('/dashboard')->with('success', 'Now impersonating '.$user->name);
    }

    public function leaveImpersonation(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        if (! $impersonatorId) {
            return redirect('/dashboard');
        }

        $impersonator = User::findOrFail($impersonatorId);
        $targetUser = $request->user();
        $request->session()->forget('impersonator_id');
        $this->auditLogger->log($impersonator->id, $request->session()->get('active_organization_id'), $request->session()->get('active_workspace_id'), 'impersonation.end', 'user', (string) $targetUser->id, ['target_email' => $targetUser->email], $request->ip(), $request->userAgent(), true);
        auth()->login($impersonator);

        return redirect('/dashboard')->with('success', 'Returned to super admin account.');
    }

    private function rolesForOrganization(?int $organizationId)
    {
        return Role::query()
            ->where(function ($query) use ($organizationId) {
                $query->whereNull('organization_id');
                if ($organizationId) {
                    $query->orWhere('organization_id', $organizationId);
                }
            })
            ->orderBy('display_name')
            ->get();
    }

    private function roleForOrganization(int $roleId, ?int $organizationId): Role
    {
        return Role::query()
            ->whereKey($roleId)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->firstOrFail();
    }

    private function assertUserVisibleToActor(Request $request, User $user): void
    {
        $actor = $request->user();
        if ($actor->isSuperAdmin()) {
            return;
        }
        $orgId = $request->session()->get('active_organization_id');
        abort_unless($orgId && $user->organizations()->where('organizations.id', $orgId)->exists(), 403, 'Unauthorized cross-organization user access.');
    }
}
