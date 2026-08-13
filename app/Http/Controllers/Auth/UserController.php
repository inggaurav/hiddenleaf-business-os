<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController
{
    public function changePassword(Request $request, User $user)
    {
        $actor = $request->user();

        // User can change their own password, or Super Admin / Company Admin can change organization member passwords
        if ((int)$actor->id !== (int)$user->id && !$actor->isSuperAdmin() && $actor->role !== 'company_admin') {
            abort(403, 'Unauthorized password change action.');
        }

        $request->validate(['password' => 'required|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated for ' . $user->name);
    }

    public function toggleStatus(Request $request, User $user)
    {
        $actor = $request->user();

        // Only Super Admin or Company Admin can toggle user active status
        if (!$actor->isSuperAdmin() && $actor->role !== 'company_admin') {
            abort(403, 'Unauthorized user status toggle action.');
        }

        // Prevent self-disable
        if ((int)$actor->id === (int)$user->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // Prevent disabling Super Admin
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin accounts cannot be deactivated.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'User account status updated.');
    }

    public function impersonate(Request $request, User $user)
    {
        $actor = $request->user();

        if (!$actor->isSuperAdmin()) {
            abort(403, 'Impersonation requires Super Administrator privileges.');
        }

        // Prevent nested impersonation
        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Nested impersonation is prohibited.');
        }

        $request->session()->put('impersonator_id', $actor->id);
        auth()->login($user);

        return redirect('/dashboard')->with('success', 'Now impersonating ' . $user->name);
    }

    public function leaveImpersonation(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId) {
            $impersonator = User::findOrFail($impersonatorId);
            $request->session()->forget('impersonator_id');
            auth()->login($impersonator);
            return redirect('/admin/companies')->with('success', 'Returned to super admin account.');
        }

        return redirect('/dashboard');
    }
}
