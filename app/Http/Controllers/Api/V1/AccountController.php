<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $locale = $validated['locale'] ?? null;
        unset($validated['locale']);
        $user->fill([
            ...$validated,
            'lang' => $locale ?? $user->lang,
        ])->save();

        return new UserResource($user->refresh());
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        return response()->json(['message' => 'Password updated and other API tokens revoked.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }

    public function tokens(Request $request)
    {
        return response()->json(['data' => $request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'created_at' => $token->created_at,
        ])]);
    }

    public function storeToken(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'max:20'],
            'abilities.*' => ['string', 'max:100'],
        ]);
        $token = $request->user()->createToken($validated['name'], $validated['abilities'] ?? ['*']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_id' => $token->accessToken->id,
        ], 201);
    }

    public function destroyToken(Request $request, int $token)
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();

        abort_unless($deleted, 404);

        return response()->noContent();
    }
}
