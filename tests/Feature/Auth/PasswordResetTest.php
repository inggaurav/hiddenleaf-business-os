<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create(['email' => 'user@hiddenleaf.io']);

        $response = $this->post('/forgot-password', [
            'email' => 'user@hiddenleaf.io',
        ]);

        $response->assertSessionHas('success');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'user@hiddenleaf.io']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@hiddenleaf.io',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(auth()->attempt([
            'email' => 'user@hiddenleaf.io',
            'password' => 'NewSecurePassword123!',
        ]));
    }
}
