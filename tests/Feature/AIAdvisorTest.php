<?php

namespace Tests\Feature;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\RiskLevel;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\Addon;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use HiddenLeaf\AIAdvisor\Domain\MrFox\Tools\AIAdvisorTool;
use HiddenLeaf\AIAdvisor\Domain\Services\AIAnalysisService;
use HiddenLeaf\AIAdvisor\Domain\Services\DataAggregationService;
use HiddenLeaf\AIAdvisor\Domain\Services\HealthScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AIAdvisorTest extends TestCase
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
            'name' => 'AI Advisor Plan',
            'modules' => ['ai-advisor'],
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
            ['alias' => 'ai-advisor'],
            [
                'addon_id' => 'hiddenleaf-ai-advisor',
                'name' => 'AI Business Advisor',
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
            'module_name' => 'ai-advisor',
        ]);
    }

    private function seedBusinessData(): void
    {
        // Financial
        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 200000,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        AccountRevenue::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 150000,
            'date' => Carbon::today()->subMonth(),
            'created_by' => $this->user->id,
        ]);

        AccountExpense::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'amount' => 80000,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        // HR
        $emp1 = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'employee_number' => 'EMP-001',
            'joined_at' => Carbon::today()->subMonths(3),
            'status' => 'active',
        ]);

        $emp2 = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->memberUser->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'employee_number' => 'EMP-002',
            'joined_at' => Carbon::today(),
            'status' => 'active',
        ]);

        HrAttendance::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_id' => $emp1->id,
            'attendance_date' => Carbon::today(),
            'status' => 'present',
            'clock_in' => Carbon::today()->setTime(9, 0),
        ]);

        // Leave
        $leaveType = HrLeaveType::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Casual Leave',
            'days_allowed' => 12,
        ]);

        HrLeaveRequest::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_id' => $emp2->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => Carbon::today()->addDays(2),
            'ends_on' => Carbon::today()->addDays(4),
            'days' => 2,
            'status' => 'pending',
            'reason' => 'Personal work',
        ]);

        // CRM
        $pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $stage = CrmStage::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Proposal',
            'position' => 1,
        ]);

        CrmLead::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'status' => 'open',
            'estimated_value' => 75000,
        ]);

        CrmDeal::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'name' => 'Software License',
            'value' => 120000,
            'status' => 'open',
        ]);

        // Projects
        $project = TasklyProject::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Platform v2',
            'status' => 'in_progress',
            'budget' => 300000,
            'due_on' => Carbon::today()->addMonths(1),
            'created_by' => $this->user->id,
        ]);

        $taskStage = TasklyStage::create([
            'project_id' => $project->id,
            'name' => 'Doing',
            'position' => 1,
        ]);

        TasklyTask::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'project_id' => $project->id,
            'stage_id' => $taskStage->id,
            'title' => 'Backend API',
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'completed_at' => Carbon::today(),
            'created_by' => $this->user->id,
        ]);

        TasklyTask::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'project_id' => $project->id,
            'stage_id' => $taskStage->id,
            'title' => 'UI Polish',
            'priority' => 'medium',
            'assigned_to' => $this->memberUser->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_data_aggregation_service(): void
    {
        $this->seedBusinessData();

        $service = app(DataAggregationService::class);
        $metrics = $service->getAllMetrics($this->workspace);

        $this->assertArrayHasKey('financial', $metrics);
        $this->assertArrayHasKey('hrm', $metrics);
        $this->assertArrayHasKey('sales', $metrics);
        $this->assertArrayHasKey('projects', $metrics);

        $fin = $metrics['financial'];
        $this->assertEquals(200000, $fin['revenue_this_month']);
        $this->assertEquals(80000, $fin['expenses_this_month']);
        $this->assertEquals(120000, $fin['profit']);
        $this->assertEquals(60.0, $fin['profit_margin_percent']);

        $hrm = $metrics['hrm'];
        $this->assertEquals(2, $hrm['total_employees']);
        $this->assertEquals(1, $hrm['new_hires_this_month']);
        $this->assertEquals(1, $hrm['pending_leave_requests']);

        $sales = $metrics['sales'];
        $this->assertEquals(1, $sales['active_leads']);
        $this->assertEquals(120000, $sales['pipeline_value']);

        $projects = $metrics['projects'];
        $this->assertEquals(1, $projects['active_projects']);
        $this->assertEquals(2, $projects['total_tasks']);
        $this->assertEquals(1, $projects['completed_tasks']);
    }

    public function test_health_score_service(): void
    {
        $this->seedBusinessData();

        $dataService = app(DataAggregationService::class);
        $metrics = $dataService->getAllMetrics($this->workspace);

        $healthService = app(HealthScoreService::class);
        $scores = $healthService->calculate($metrics);

        $this->assertArrayHasKey('score', $scores);
        $this->assertArrayHasKey('financial_score', $scores);
        $this->assertArrayHasKey('team_score', $scores);
        $this->assertArrayHasKey('sales_score', $scores);
        $this->assertArrayHasKey('project_score', $scores);
        $this->assertArrayHasKey('operations_score', $scores);

        $this->assertGreaterThanOrEqual(0, $scores['score']);
        $this->assertLessThanOrEqual(100, $scores['score']);
        $this->assertGreaterThan(50, $scores['financial_score']); // high profit margin + growth
    }

    public function test_ai_analysis_service_fallback(): void
    {
        $this->seedBusinessData();

        $dataService = app(DataAggregationService::class);
        $metrics = $dataService->getAllMetrics($this->workspace);

        $healthService = app(HealthScoreService::class);
        $healthScore = $healthService->calculate($metrics);

        $aiService = app(AIAnalysisService::class);
        $insights = $aiService->generateFallbackInsights($metrics, $healthScore);

        $this->assertArrayHasKey('insights', $insights);
        $this->assertArrayHasKey('recommendations', $insights);
        $this->assertArrayHasKey('alerts', $insights);
        $this->assertNotEmpty($insights['insights']);
    }

    public function test_http_dashboard_endpoint(): void
    {
        $this->seedBusinessData();

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/ai-advisor/dashboard');

        $response->assertStatus(200);
    }

    public function test_http_history_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/ai-advisor/history');

        $response->assertStatus(200);
    }

    public function test_http_analyze_endpoint(): void
    {
        $this->seedBusinessData();

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post('/ai-advisor/analyze');

        $response->assertRedirect('/ai-advisor/dashboard');

        $this->assertDatabaseHas('ai_health_scores', [
            'workspace_id' => $this->workspace->id,
            'organization_id' => $this->organization->id,
        ]);

        $score = DB::table('ai_health_scores')->where('workspace_id', $this->workspace->id)->first();
        $this->assertNotNull($score);

        $this->assertDatabaseHas('ai_insights', [
            'health_score_id' => $score->id,
        ]);
    }

    public function test_mrfox_ai_advisor_tool(): void
    {
        $this->seedBusinessData();

        $tool = new AIAdvisorTool();
        $this->assertEquals('ai_advisor.summary', $tool->name());
        $this->assertEquals('ai-advisor.view', $tool->requiredPermission());
        $this->assertEquals('ai-advisor', $tool->requiredModule());
        $this->assertEquals(RiskLevel::READ, $tool->riskLevel());

        $context = new ToolContext(
            user: $this->user,
            organization: $this->organization,
            workspace: $this->workspace
        );

        $result = $tool->execute($context, []);
        $this->assertTrue($result->success);
        $this->assertArrayHasKey('health_score', $result->data);
        $this->assertArrayHasKey('insights', $result->data);
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
            'amount' => 50000,
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

        $service = app(DataAggregationService::class);

        $ws1 = $service->getFinancialMetrics($this->workspace);
        $this->assertEquals(50000, $ws1['revenue_this_month']);

        $ws2 = $service->getFinancialMetrics($otherWorkspace);
        $this->assertEquals(999999, $ws2['revenue_this_month']);
    }
}
