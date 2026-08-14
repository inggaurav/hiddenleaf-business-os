<?php

namespace App\Http\Controllers\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Verify account exists & is active
        $user = User::where('email', $credentials['email'])->first();
        if ($user && ! $user->is_active) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact support.',
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            if (! $request->session()->has('active_workspace_id')) {
                $workspace = $user->isSuperAdmin()
                    ? Workspace::query()->oldest('id')->first()
                    : $user->workspaces()->oldest('workspaces.id')->first();

                if ($workspace) {
                    $request->session()->put('active_organization_id', $workspace->organization_id);
                    $request->session()->put('active_workspace_id', $workspace->id);
                    $request->session()->put('active_workspace_title', $workspace->name);
                }
            }

            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($throttleKey);

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

        // Wrap full registration & provisioning in DB transaction (Section J)
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $org = Organization::create([
                'name' => $user->name."'s Org",
                'slug' => 'org-'.bin2hex(random_bytes(4)),
                'owner_id' => $user->id,
                'is_active' => true,
            ]);

            $ws = Workspace::create([
                'organization_id' => $org->id,
                'name' => 'Main Operations',
                'slug' => 'main-operations',
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            $user->organizations()->attach($org->id, ['role' => 'owner']);
            $user->workspaces()->attach($ws->id);

            return $user;
        });

        Auth::login($user);

        $firstOrg = $user->organizations()->first();
        $firstWs = $user->workspaces()->first();

        $request->session()->put('active_organization_id', $firstOrg->id);
        $request->session()->put('active_workspace_id', $firstWs->id);
        $request->session()->put('active_workspace_title', $firstWs->name);

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
