<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\BusinessDashboardSummaryTool;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MrFoxToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_tool_registration_is_rejected(): void
    {
        $registry = new MrFoxToolRegistry;
        $registry->register(new BusinessDashboardSummaryTool);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tool 'business.dashboard.summary' is already registered");

        $registry->register(new BusinessDashboardSummaryTool);
    }

    public function test_disabled_module_tools_are_filtered_out(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'member']);
        $plan = Plan::create(['name' => 'Minimal Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'member']);
        $workspace->members()->attach($user);

        // Only activate CRM module
        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'crm']);

        $registry = app(MrFoxToolRegistry::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        $available = $registry->availableFor($context);

        // CRM tools should be available
        $this->assertArrayHasKey('crm.search.leads', $available);

        // Accounting & HR tools should NOT be available
        $this->assertArrayNotHasKey('accounting.pnl', $available);
        $this->assertArrayNotHasKey('hr.employee.summary', $available);
    }
}
