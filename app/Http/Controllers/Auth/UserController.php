<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    public function changePassword(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if ((int)$actor->id !== (int)$user->id) {
            if (!$actor->isSuperAdmin()) {
                $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
                if (!$sharesOrg) {
                    abort(403, 'Unauthorized cross-organization user password mutation.');
                }

                $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
                if (!$actor->canInWorkspace('users.change_password', $workspace)) {
                    abort(403, 'Unauthorized to change user passwords.');
                }
            }
        }

        $request->validate(['password' => 'required|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated for ' . $user->name);
    }

    public function toggleStatus(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if (!$actor->isSuperAdmin()) {
            $sharesOrg = $user->organizations()->where('organizations.id', $orgId)->exists();
            if (!$sharesOrg) {
                abort(403, 'Unauthorized cross-organization user status mutation.');
            }

            $workspace = Workspace::where('id', $wsId)->where('organization_id', $orgId)->firstOrFail();
            if (!$actor->canInWorkspace('users.toggle_status', $workspace)) {
                abort(403, 'Unauthorized to toggle user account status.');
            }
        }

        if ((int)$actor->id === (int)$user->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin accounts cannot be deactivated.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'User account status updated.');
    }

    public function impersonate(Request $request, User $user)
    {
        $actor = $request->user();
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        if (!$actor->isSuperAdmin()) {
            abort(403, 'Impersonation requires Super Administrator privileges.');
        }

        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Nested impersonation is prohibited.');
        }

        $request->session()->put('impersonator_id', $actor->id);

        // Audit Log Impersonation Start (Section 8)
        $this->auditLogger->log(
            $actor->id,
            $orgId,
            $wsId,
            'impersonation.start',
            'user',
            (string)$user->id,
            ['target_email' => $user->email],
            $request->ip(),
            $request->userAgent()
        );

        auth()->login($user);

        return redirect('/dashboard')->with('success', 'Now impersonating ' . $user->name);
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

            // Audit Log Impersonation Ended (Section 8)
            $this->auditLogger->log(
                $impersonator->id,
                $orgId,
                $wsId,
                'impersonation.end',
                'user',
                (string)$targetUser->id,
                ['target_email' => $targetUser->email],
                $request->ip(),
                $request->userAgent()
            );

            auth()->login($impersonator);
            return redirect('/dashboard')->with('success', 'Returned to super admin account.');
        }

        return redirect('/dashboard');
    }
}
