<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginDetail;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    public function index(Request $request)
    {
        $actor = Auth::user();
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $users = User::query()
            ->when(! $actor->isSuperAdmin(), function ($q) use ($orgId) {
                $q->whereHas('organizations', fn ($query) => $query->where('organizations.id', $orgId));
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        $roles = Role::all();
        $plans = Plan::where('status', true)->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'plans' => $plans,
            'isSuperAdmin' => $actor->isSuperAdmin(),
        ]);
    }

    public function create()
    {
        $roles = Role::all();

        return Inertia::render('Users/Create', ['roles' => $roles]);
    }

    public function store(Request $request)
    {
        $actor = Auth::user();
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($orgId) {
            $user->organizations()->attach($orgId, ['role' => $validated['role']]);
        }

        if ($wsId) {
            $user->workspaces()->attach($wsId);
        }

        if (! empty($validated['plan_id'])) {
            $plan = Plan::find($validated['plan_id']);
            if ($plan) {
                assignPlan($plan->id, 'Month', $plan->modules ?? [], [], $user->id);
            }
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user)
    {
        $roles = Role::all();

        return Inertia::render('Users/Edit', ['user' => $user, 'roles' => $roles]);
    }

    public function update(Request $request, User $user)
    {
        $actor = Auth::user();
        $orgId = session('active_organization_id');

        if (! $actor->isSuperAdmin()) {
            $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
            if (! $sharesOrg) {
                abort(403, 'Unauthorized.');
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'role' => 'nullable|string',
        ]);

        $user->update(array_filter($validated));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $actor = Auth::user();
        $orgId = session('active_organization_id');

        if (! $actor->isSuperAdmin()) {
            $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
            if (! $sharesOrg) {
                abort(403, 'Unauthorized.');
            }
        }

        if ($user->id === $actor->id) {
            return back()->with('error', 'Cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function assignPlan(Request $request, User $user)
    {
        $actor = Auth::user();
        if (! $actor->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'duration' => 'nullable|in:Month,Year,Lifetime',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $duration = $validated['duration'] ?? 'Month';

        assignPlan($plan->id, $duration, $plan->modules ?? [], [], $user->id);

        return back()->with('success', "Plan {$plan->name} assigned to {$user->name}.");
    }

    public function loginHistory(Request $request)
    {
        $actor = Auth::user();

        $logs = LoginDetail::with('user')
            ->when(! $actor->isSuperAdmin(), function ($q) use ($actor) {
                $q->where('user_id', $actor->id);
            })
            ->latest()
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Users/LoginHistory', [
            'logs' => $logs,
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if ((int) $actor->id !== (int) $user->id) {
            if (! $actor->isSuperAdmin()) {
                $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
                if (! $sharesOrg) {
                    abort(403, 'Unauthorized cross-organization user password mutation.');
                }

                $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
                if (! $actor->canInWorkspace('users.change_password', $workspace)) {
                    abort(403, 'Unauthorized to change user passwords.');
                }
            }
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
            $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
            if (! $sharesOrg) {
                abort(403, 'Unauthorized cross-organization user status mutation.');
            }

            $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
            if (! $actor->canInWorkspace('users.toggle_status', $workspace)) {
                abort(403, 'Unauthorized to toggle user account status.');
            }
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
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if (! $actor->isSuperAdmin()) {
            abort(403, 'Impersonation requires Super Administrator privileges.');
        }

        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Nested impersonation is prohibited.');
        }

        $request->session()->put('impersonator_id', $actor->id);

        // Audit Log Impersonation Start
        $this->auditLogger->log(
            $actor->id,
            $orgId,
            $wsId,
            'impersonation.start',
            'user',
            (string) $user->id,
            ['target_email' => $user->email],
            $request->ip(),
            $request->userAgent(),
            true,
        );

        auth()->login($user);

        return redirect('/dashboard')->with('success', 'Now impersonating '.$user->name);
    }

    public function leaveImpersonation(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if ($impersonatorId) {
            $impersonator = User::findOrFail($impersonatorId);
            $targetUser = $request->user();

            $request->session()->forget('impersonator_id');

            // Audit Log Impersonation Ended
            $this->auditLogger->log(
                $impersonator->id,
                $orgId,
                $wsId,
                'impersonation.end',
                'user',
                (string) $targetUser->id,
                ['target_email' => $targetUser->email],
                $request->ip(),
                $request->userAgent(),
                true,
            );

            auth()->login($impersonator);

            return redirect('/dashboard')->with('success', 'Returned to super admin account.');
        }

        return redirect('/dashboard');
    }
}
