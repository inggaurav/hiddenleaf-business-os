<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Workspace;

class ModuleActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_module_activation()
    {
        $user = User::factory()->create();
        $org = \App\Models\Organization::factory()->create();
        $workspace = Workspace::factory()->create(['organization_id' => $org->id]);
        $user->organizations()->attach($org);
        $user->workspaces()->attach($workspace);

        $this->actingAs($user)->withSession([
            'active_workspace_id' => $workspace->id,
            'active_organization_id' => $org->id
        ]);

        // Enable
        $response = $this->post(route('modules.toggle'), [
            'module_name' => 'HR',
            'active' => true
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'HR'
        ]);

        // Disable
        $response = $this->post(route('modules.toggle'), [
            'module_name' => 'HR',
            'active' => false
        ]);
        $response->assertStatus(302);
        $this->assertDatabaseMissing('user_active_modules', [
            'workspace_id' => $workspace->id,
            'module_name' => 'HR'
        ]);
    }
}
