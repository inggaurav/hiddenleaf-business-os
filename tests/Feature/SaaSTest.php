<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_plan()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'super_admin']);
        $response = $this->actingAs($user)->post('/plans', [
            'name' => 'Pro',
            'price_monthly' => 10,
            'price_yearly' => 100,
            'max_users' => 5,
            'max_storage' => 100,
            'trial_days' => 7,
        ]);

        $response->assertRedirect('/plans');
        $this->assertDatabaseHas('plans', ['name' => 'Pro']);
    }
}
