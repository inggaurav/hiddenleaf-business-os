<?php

namespace Tests\Feature\Modules;

use App\Models\LandingSite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'Landing', 'modules' => ['landingpage'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->org = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->ws = Workspace::factory()->create(['organization_id' => $this->org->id]);
        $this->org->members()->attach($this->owner, ['role' => 'owner']);
        $this->ws->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->ws->id, 'module_name' => 'landingpage']);
    }

    public function test_content_publishing_and_public_visibility(): void
    {
        $this->req()->post('/landing/sites', ['name' => 'Main', 'slug' => 'hiddenleaf', 'title' => 'HiddenLeaf', 'locale' => 'en'])->assertSessionHasNoErrors();
        $site = LandingSite::sole();
        $this->get('/site/hiddenleaf')->assertNotFound();
        $this->req()->post("/landing/sites/{$site->id}/sections", ['type' => 'hero', 'heading' => 'Operate better', 'content' => ['cta' => 'Start'], 'position' => 0, 'is_visible' => true])->assertSessionHasNoErrors();
        $this->req()->post("/landing/sites/{$site->id}/pages", ['slug' => 'privacy', 'title' => 'Privacy', 'content' => 'Privacy content', 'is_published' => true, 'position' => 1])->assertSessionHasNoErrors();
        $this->req()->post("/landing/sites/{$site->id}/publish", ['published' => true])->assertSessionHasNoErrors();
        $this->get('/site/hiddenleaf')->assertOk();
        $this->get('/site/hiddenleaf/privacy')->assertOk();
    }

    public function test_foreign_site_cannot_be_mutated(): void
    {
        $foreign = LandingSite::create(['name' => 'Foreign', 'slug' => 'foreign', 'title' => 'Foreign', 'organization_id' => Organization::factory()->create(['owner_id' => $this->owner->id])->id, 'workspace_id' => Workspace::factory()->create()->id]);
        $this->req()->post("/landing/sites/{$foreign->id}/publish", ['published' => true])->assertNotFound();
    }

    private function req(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id]);
    }
}
