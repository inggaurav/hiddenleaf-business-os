<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\BrandProfileGetTool;
use App\Models\MrFoxBrandProfile;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxBrandProfileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_profile_tool_retrieves_active_voice_and_guidelines(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Brand Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        MrFoxBrandProfile::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'name' => 'HiddenLeaf Agency',
            'industry' => 'Digital Marketing & AI Automation',
            'tagline' => 'Operate at the speed of intelligence.',
            'mission' => 'Empower modern businesses with autonomous operational clarity.',
            'tone_of_voice' => [
                'archetype' => 'The Visionary Strategist',
                'adjectives' => ['Authoritative', 'Precise', 'Modern', 'Reliable'],
                'avoid_words' => ['synergy', 'cheap', 'paradigm shift'],
            ],
            'is_default' => true,
        ]);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        $tool = app(BrandProfileGetTool::class);
        $res = $tool->execute($context, []);

        $this->assertTrue($res->success);
        $this->assertEquals('HiddenLeaf Agency', $res->data['name']);
        $this->assertEquals('The Visionary Strategist', $res->data['tone_of_voice']['archetype']);
        $this->assertNotEmpty($res->evidence);
        $this->assertEquals('brand_profile', $res->evidence[0]['type']);
    }
}
