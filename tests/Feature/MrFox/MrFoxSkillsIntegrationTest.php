<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\AiResponse;
use App\Domain\MrFox\Providers\FakeAiProvider;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Tools\SkillExecuteTool;
use App\Domain\MrFox\Tools\SkillListTool;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxSkillsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_skills_tools_list_and_execute_with_automated_quality_review(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Skills Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        // 1. Test SkillListTool
        $listTool = app(SkillListTool::class);
        $listRes = $listTool->execute($context, []);
        $this->assertTrue($listRes->success);
        $this->assertGreaterThanOrEqual(5, count($listRes->data));

        // 2. Test SkillExecuteTool with Mock Provider
        $fakeProvider = new FakeAiProvider;
        $fakeProvider->queueResponse(new AiResponse(
            content: '🚀 Boost your agency revenue with automated client workflows! #AgencyGrowth #Automation',
            provider: 'fake',
            model: 'fake-skills-v1'
        ));

        app(ProviderRouter::class)->setFakeProvider($fakeProvider);

        $execTool = app(SkillExecuteTool::class);
        $execRes = $execTool->execute($context, [
            'skill_id' => 'social_post',
            'parameters' => [
                'platform' => 'LinkedIn',
                'topic' => 'Automated Agency Workflows',
                'tone' => 'Inspirational',
            ],
        ]);

        $this->assertTrue($execRes->success);
        $this->assertEquals('social_post', $execRes->data['skill_id']);
        $this->assertStringContainsString('Boost your agency revenue', $execRes->data['content']);
        $this->assertGreaterThanOrEqual(85, $execRes->data['review_score']);
        $this->assertEquals('approved', $execRes->data['review_status']);
    }
}
