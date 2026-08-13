<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_switch_fails_for_unauthorized_organization_workspace(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Org A']);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'name' => 'WS A']);

        $orgB = Organization::factory()->create(['name' => 'Org B']);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'name' => 'WS B']);

        $userA = User::factory()->create();

        // User A belongs to Org A
        $response = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id])
            ->post('/workspaces/switch', ['workspace_id' => $wsB->id]);

        $response->assertStatus(403);
    }
}
