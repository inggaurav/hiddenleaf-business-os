<?php

namespace Tests\Feature\Automation;

use App\Domain\Automation\Execution\AutomationEngine;
use App\Domain\Automation\Missions\MissionExecutor;
use App\Domain\Automation\Missions\MissionPlanner;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\AiResponse;
use App\Domain\MrFox\Providers\FakeAiProvider;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Models\AutomationRule;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\MrFoxBrandProfile;
use App\Models\MrFoxMission;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductService;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedAutomationMissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_overdue_and_inventory_low_stock_automations(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan A', 'modules' => ['account', 'taskly', 'productservice'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $engine = app(AutomationEngine::class);

        // 1. Automation: Overdue Invoice -> Create Collection Task & Draft Communication
        AutomationRule::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Overdue Invoice Collection',
            'trigger_type' => 'sales.invoice.overdue',
            'condition_config' => [
                'field' => 'overdue_days',
                'operator' => '>=',
                'value' => 3,
            ],
            'action_config' => [
                [
                    'action' => 'tasks.create_task',
                    'input' => ['title' => 'Collect overdue invoice #{{invoice_number}}', 'priority' => 'high'],
                ],
            ],
            'enabled' => true,
        ]);

        $runs = $engine->dispatch($ws->id, 'sales.invoice.overdue', [
            'invoice_id' => 42,
            'invoice_number' => 'INV-0042',
            'overdue_days' => 5,
            'customer_name' => 'Stark Industries',
        ]);

        $this->assertCount(1, $runs);
        $this->assertEquals('completed', $runs[0]->status);
        $this->assertDatabaseHas('taskly_tasks', ['workspace_id' => $ws->id, 'title' => 'Collect overdue invoice #INV-0042']);

        // 2. Automation: Low Stock -> Create Restock Task
        AutomationRule::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Reorder Low Inventory',
            'trigger_type' => 'inventory.stock.low',
            'condition_config' => [
                'field' => 'quantity',
                'operator' => '<=',
                'value' => 5,
            ],
            'action_config' => [
                [
                    'action' => 'tasks.create_task',
                    'input' => ['title' => 'Restock item {{product_name}} (Qty: {{quantity}})', 'priority' => 'high'],
                ],
            ],
            'enabled' => true,
        ]);

        $stockRuns = $engine->dispatch($ws->id, 'inventory.stock.low', [
            'id' => 88,
            'product_name' => 'Widget A',
            'quantity' => 2,
        ]);

        $this->assertCount(1, $stockRuns);
        $this->assertEquals('completed', $stockRuns[0]->status);
        $this->assertDatabaseHas('taskly_tasks', ['workspace_id' => $ws->id, 'title' => 'Restock item Widget A (Qty: 2)']);
    }

    public function test_marketing_mission_with_brand_profile_and_skills(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan M', 'modules' => ['crm', 'taskly'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $brand = MrFoxBrandProfile::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'HyperGrowth Marketing',
            'industry' => 'SaaS',
            'tone_of_voice' => ['archetype' => 'Energetic, modern, and data-driven'],
            'is_default' => true,
        ]);

        $fake = new FakeAiProvider();
        $fake->queueResponse(new AiResponse(
            content: json_encode([
                ['tool' => 'brand_profile.get', 'params' => ['profile_id' => $brand->id], 'description' => 'Fetch brand persona'],
                ['tool' => 'skill.list', 'params' => [], 'description' => 'Discover available marketing skills'],
            ]),
            provider: 'fake'
        ));
        app(ProviderRouter::class)->setFakeProvider($fake);

        $mission = MrFoxMission::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'brand_profile_id' => $brand->id,
            'name' => 'Weekly Content Preparation',
            'objective' => 'Prepare weekly marketing copy aligned with Brand Profile.',
            'status' => 'draft',
            'allowed_tools' => ['brand_profile.get', 'skill.list'],
        ]);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $ws);

        $planner = app(MissionPlanner::class);
        $planner->plan($context, $mission);

        $executor = app(MissionExecutor::class);
        $res1 = $executor->runNextStep($context, $mission->fresh());
        $this->assertEquals('running', $res1['status']);
        $this->assertEquals('brand_profile.get', $res1['tool']);

        $res2 = $executor->runNextStep($context, $mission->fresh());
        $this->assertEquals('completed', $res2['status']);
        $this->assertEquals('skill.list', $res2['tool']);
        $this->assertEquals('completed', $mission->fresh()->status);
    }
}
