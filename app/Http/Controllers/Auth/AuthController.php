<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\TenantProvisioningService;
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

            return back()->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
        }

        $user = User::where('email', $credentials['email'])->first();
        if ($user && ! $user->is_active) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            $request->session()->forget(['enabled_modules', 'active_organization_id', 'active_workspace_id', 'active_workspace_title']);

            if ($user->isSuperAdmin()) {
                return redirect()->intended('/super-admin/dashboard');
            }

            $workspace = $user->workspaces()
                ->where('workspaces.is_active', true)
                ->whereHas('organization', fn ($query) => $query->where('is_active', true))
                ->oldest('workspaces.id')
                ->first();

            if ($workspace) {
                $request->session()->put('active_organization_id', $workspace->organization_id);
                $request->session()->put('active_workspace_id', $workspace->id);
                $request->session()->put('active_workspace_title', $workspace->name);
            }

            if (in_array($user->role, ['client', 'customer', 'vendor'], true)) {
                return redirect()->intended('/portal/dashboard');
            }

            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($throttleKey);

        return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
    }

    public function registerView()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request, TenantProvisioningService $provisioner)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        [$user, $workspace] = DB::transaction(function () use ($validated, $provisioner) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $provisioned = $provisioner->provision($user, [
                'company_name' => $validated['name']."'s Organization",
                'timezone' => config('app.timezone', 'UTC'),
            ]);

            return [$user, $provisioned['workspace']];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('enabled_modules');
        $request->session()->put('active_organization_id', $workspace->organization_id);
        $request->session()->put('active_workspace_id', $workspace->id);
        $request->session()->put('active_workspace_title', $workspace->name);

        return redirect('/onboarding')->with('success', 'Welcome to HiddenLeaf Business OS. Complete your business setup to get started.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
