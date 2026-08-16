<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@hiddenleaf.io',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@hiddenleaf.io',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    public function test_users_can_register_and_auto_provision_organization(): void
    {
        $response = $this->post('/register', [
            'name' => 'Gaurav User',
            'email' => 'gaurav@hiddenleaf.io',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'gaurav@hiddenleaf.io']);
        $this->assertDatabaseHas('organizations', ['name' => "Gaurav User's Organization"]);
        $this->assertDatabaseHas('workspaces', ['name' => 'Primary Workspace']);
    }
}
