<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController
{
    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string',
            'lang' => 'required|string|max:10',
            'theme' => 'required|in:light,dark,system',
            'avatar' => 'nullable|image|max:2048',
            'current_password' => 'nullable|required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check((string) $validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.']);
            }
        } else {
            unset($validated['password']);
        }
        unset($validated['current_password']);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Account deleted.');
    }
}
