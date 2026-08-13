<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiTokenController
{
    public function index(Request $request)
    {
        return Inertia::render('Settings/ApiTokens', [
            'tokens' => $request->user()->tokens,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($validated['name']);

        return back()->with('flash', [
            'token' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, $tokenId)
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back();
    }
}
