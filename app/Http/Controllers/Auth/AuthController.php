<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class AuthController
{
    public function loginView()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function registerView()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'company_admin',
        ]);

        $org = Organization::create([
            'name' => $user->name . "'s Org",
            'slug' => 'org-' . bin2hex(random_bytes(4)),
            'owner_id' => $user->id,
        ]);

        $ws = Workspace::create([
            'organization_id' => $org->id,
            'name' => 'Main Operations',
            'slug' => 'main-operations',
            'created_by' => $user->id,
        ]);

        $user->organizations()->attach($org->id, ['role' => 'owner']);
        $user->workspaces()->attach($ws->id);

        Auth::login($user);

        $request->session()->put('active_organization_id', $org->id);
        $request->session()->put('active_workspace_id', $ws->id);
        $request->session()->put('active_workspace_title', $ws->name);

        return redirect('/dashboard')->with('success', 'Welcome to HiddenLeaf BusinessOS!');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
