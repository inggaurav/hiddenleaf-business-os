<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController
{
    public function changePassword(Request $request, User $user)
    {
        $request->validate(['password' => 'required|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated for ' . $user->name);
    }

    public function impersonate(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized impersonation attempt.');
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        auth()->login($user);

        return redirect('/dashboard')->with('success', 'Impersonating ' . $user->name);
    }

    public function leaveImpersonation(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        if ($impersonatorId) {
            $impersonator = User::findOrFail($impersonatorId);
            $request->session()->forget('impersonator_id');
            auth()->login($impersonator);
        }

        return redirect('/admin/companies')->with('success', 'Returned to super admin account.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', 'User status updated.');
    }
}
