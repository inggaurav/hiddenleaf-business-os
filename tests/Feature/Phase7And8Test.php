<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7And8Test extends TestCase
{
    use RefreshDatabase;

    protected function setupUserWithTenant()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id]);
        $user->organizations()->attach($org->id);
        $user->workspaces()->attach($ws->id);

        return $user;
    }

    public function test_can_view_email_templates_page()
    {
        $user = $this->setupUserWithTenant();
        $response = $this->actingAs($user)->get(route('settings.email-templates.index'));
        $response->assertStatus(200);
    }

    public function test_can_create_email_template()
    {
        $user = $this->setupUserWithTenant();
        $response = $this->actingAs($user)->post(route('settings.email-templates.store'), [
            'name' => 'Welcome Email',
            'subject' => 'Welcome to our platform',
            'body' => '<p>Hello!</p>',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('email_templates', [
            'name' => 'Welcome Email',
        ]);
    }

    public function test_can_view_api_tokens_page()
    {
        $user = $this->setupUserWithTenant();
        $response = $this->actingAs($user)->get(route('settings.api-tokens.index'));
        $response->assertStatus(200);
    }

    public function test_can_create_api_token()
    {
        $user = $this->setupUserWithTenant();
        $response = $this->actingAs($user)->post(route('settings.api-tokens.store'), [
            'name' => 'Test Token',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Test Token',
        ]);
    }
}
