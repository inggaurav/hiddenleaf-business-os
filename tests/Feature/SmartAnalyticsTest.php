<?php

namespace Tests\Feature;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\Addon;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use HiddenLeaf\SmartAnalytics\Domain\MrFox\Tools\SmartAnalyticsSummaryTool;
use HiddenLeaf\SmartAnalytics\Domain\Services\ExecutiveOverviewService;
use HiddenLeaf\SmartAnalytics\Domain\Services\FinancialAnalyticsService;
use HiddenLeaf\SmartAnalytics\Domain\Services\OperationalAnalyticsService;
use HiddenLeaf\SmartAnalytics\Domain\Services\SalesAnalyticsService;
use HiddenLeaf\SmartAnalytics\Domain\Services\TeamPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SmartAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $memberUser;
    private Organization $organization;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $this->memberUser = User::factory()->create(['role' => 'employee']);

        $plan = Plan::create([
            'name' => 'Smart Analytics Plan',
            'modules' => ['smart-analytics'],
            'status' => true,
            'created_by' => $this->user->id,
        ]);

        $this->organization = Organization::factory()->create([
            'owner_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $this->workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->organization->members()->attach($this->memberUser, ['role' => 'member']);
        $this->workspace->members()->attach($this->user);
        $this->workspace->members()->attach($this->memberUser);

        $addon = Addon::firstOrCreate(
            ['alias' => 'smart-analytics'],
            [
                'addon_id' => 'hiddenleaf-smart-analytics',
                'name' => 'Smart Dashboard Analytics',
                'status' => 'installed',
                'version' => '1.0.0',
                'minimum_core' => '1.0.0',
                'dependencies' => [],
                'manifest' => [],
            ]
        );

        WorkspaceAddon::firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'addon_id' => $addon->id],
            ['is_active' => true]
        );

        UserActiveModule::firstOrCreate([
            'workspace_id' => $this->workspace->id,
            'module_name' => 'smart-analytics',
        ]);
    }

    private function seedFinancialData(): void
    {
        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 150000,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 100000,
            'date' => Carbon::today()->subMonth(),
            'created_by' => $this->user->id,
        ]);

        AccountExpense::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 50000,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);
    }

    private function seedHrData(): void
    {
        HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'name' => 'Admin Employee',
            'email' => 'admin@test.com',
            'employee_number' => 'EMP-001',
            'joined_at' => Carbon::today()->subMonths(6),
            'status' => 'active',
        ]);

        HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->memberUser->id,
            'name' => 'Member Employee',
            'email' => 'member@test.com',
            'employee_number' => 'EMP-002',
            'joined_at' => Carbon::today(),
            'status' => 'active',
        ]);
    }

    private function seedCrmData(): void
    {
        $pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Default Pipeline',
            'is_default' => true,
        ]);

        $stage = CrmStage::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualified',
            'position' => 1,
        ]);

        CrmLead::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Test Lead',
            'email' => 'lead@test.com',
            'status' => 'open',
            'estimated_value' => 50000,
        ]);

        CrmDeal::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Big Deal',
            'value' => 200000,
            'status' => 'open',
        ]);
    }

    private function seedProjectData(): void
    {
        $project = TasklyProject::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Alpha Project',
            'status' => 'in_progress',
            'budget' => 500000,
            'created_by' => $this->user->id,
        ]);

        $stage = TasklyStage::create([
            'project_id' => $project->id,
            'name' => 'In Progress',
            'position' => 1,
        ]);

        TasklyTask::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'project_id' => $project->id,
            'stage_id' => $stage->id,
            'title' => 'Completed Task',
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        TasklyTask::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'project_id' => $project->id,
            'stage_id' => $stage->id,
            'title' => 'Overdue Task',
            'priority' => 'critical',
            'assigned_to' => $this->memberUser->id,
            'due_on' => Carbon::yesterday(),
            'created_by' => $this->user->id,
        ]);
    }

    public function test_executive_overview_service_returns_correct_structure(): void
    {
        $this->seedFinancialData();
        $this->seedHrData();
        $this->seedCrmData();
        $this->seedProjectData();

        $service = app(ExecutiveOverviewService::class);
        $data = $service->getOverviewData($this->workspace);

        $this->assertArrayHasKey('kpi_cards', $data);
        $this->assertArrayHasKey('quick_insights', $data);
        $this->assertArrayHasKey('module_summaries', $data);
        $this->assertArrayHasKey('top_customers', $data);
        $this->assertArrayHasKey('recent_transactions', $data);

        $kpis = $data['kpi_cards'];
        $this->assertEquals(150000, $kpis['revenue']['current']);
        $this->assertEquals(100000, $kpis['revenue']['previous']);
        $this->assertEquals(50.0, $kpis['revenue']['growth']);
        $this->assertEquals(100000, $kpis['profit']['net']);
        $this->assertEquals(2, $kpis['employees']['active']);
        $this->assertEquals(1, $kpis['employees']['new_hires']);
    }

    public function test_financial_analytics_service(): void
    {
        $this->seedFinancialData();

        $service = app(FinancialAnalyticsService::class);
        $data = $service->getFinancialData($this->workspace);

        $this->assertArrayHasKey('revenue_analysis', $data);
        $this->assertArrayHasKey('expense_analysis', $data);
        $this->assertArrayHasKey('profitability', $data);
        $this->assertEquals(150000, $data['revenue_analysis']['current']);
        $this->assertEquals(50000, $data['expense_analysis']['current']);
        $this->assertEquals(100000, $data['profitability']['net_profit']);
    }

    public function test_team_performance_service(): void
    {
        $this->seedHrData();
        $this->seedProjectData();

        $service = app(TeamPerformanceService::class);
        $data = $service->getTeamData($this->workspace);

        $this->assertArrayHasKey('overview', $data);
        $this->assertArrayHasKey('attendance', $data);
        $this->assertArrayHasKey('top_performers', $data);
        $this->assertEquals(2, $data['overview']['total_employees']);
        $this->assertEquals(2, $data['overview']['active_employees']);
    }

    public function test_sales_analytics_service(): void
    {
        $this->seedCrmData();

        $service = app(SalesAnalyticsService::class);
        $data = $service->getSalesData($this->workspace);

        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('pipeline_funnel', $data);
        $this->assertEquals(200000, $data['kpis']['pipeline_value']);
        $this->assertEquals(1, $data['kpis']['open_leads']);
        $this->assertEquals(1, $data['kpis']['total_deals']);
    }

    public function test_operational_analytics_service(): void
    {
        $this->seedProjectData();

        $service = app(OperationalAnalyticsService::class);
        $data = $service->getOperationsData($this->workspace);

        $this->assertArrayHasKey('project_metrics', $data);
        $this->assertArrayHasKey('task_metrics', $data);
        $this->assertEquals(1, $data['project_metrics']['active']);
        $this->assertEquals(2, $data['task_metrics']['total']);
        $this->assertEquals(1, $data['task_metrics']['completed']);
        $this->assertEquals(1, $data['task_metrics']['overdue']);
    }

    public function test_http_dashboard_endpoint(): void
    {
        $this->seedFinancialData();

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/smart-analytics/dashboard');

        $response->assertStatus(200);
    }

    public function test_http_financial_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/smart-analytics/financial');

        $response->assertStatus(200);
    }

    public function test_http_team_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/smart-analytics/team');

        $response->assertStatus(200);
    }

    public function test_http_sales_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/smart-analytics/sales');

        $response->assertStatus(200);
    }

    public function test_http_operations_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/smart-analytics/operations');

        $response->assertStatus(200);
    }

    public function test_mrfox_smart_analytics_summary_tool(): void
    {
        $this->seedFinancialData();
        $this->seedCrmData();

        $tool = new SmartAnalyticsSummaryTool();
        $this->assertEquals('smart_analytics.summary', $tool->name());
        $this->assertEquals('smart-analytics.view', $tool->requiredPermission());
        $this->assertEquals('smart-analytics', $tool->requiredModule());

        $context = new ToolContext(
            user: $this->user,
            organization: $this->organization,
            workspace: $this->workspace
        );

        $result = $tool->execute($context, []);
        $this->assertTrue($result->success);

        $data = $result->data;
        $this->assertArrayHasKey('kpis', $data);
        $this->assertEquals(150000, $data['kpis']['revenue']['current']);
    }

    public function test_workspace_isolation(): void
    {
        $otherWorkspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 100000,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $otherWorkspace->id,
            'amount' => 999999,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        $service = app(ExecutiveOverviewService::class);

        $ws1Data = $service->getOverviewData($this->workspace);
        $this->assertEquals(100000, $ws1Data['kpi_cards']['revenue']['current']);

        $ws2Data = $service->getOverviewData($otherWorkspace);
        $this->assertEquals(999999, $ws2Data['kpi_cards']['revenue']['current']);
    }
}
