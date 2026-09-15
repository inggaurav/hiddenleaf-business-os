<?php

namespace Tests\Feature;

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
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmKanbanAndHrmGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private CrmPipeline $pipeline;
    private CrmStage $stage1;
    private CrmStage $stage2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);

        $plan = Plan::create([
            'name' => 'CRM Pro Plan',
            'modules' => ['lead', 'crm-deals-kanban', 'hrm'],
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
        $this->workspace->members()->attach($this->user);

        \App\Models\UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'lead']);
        \App\Models\UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'hrm']);
        \App\Models\UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'crm-deals-kanban']);

        // Seed Pipeline & Stages
        $this->pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Default Sales Pipeline',
            'is_default' => true,
        ]);

        $this->stage1 = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Qualified',
            'position' => 1,
            'probability' => 20,
        ]);

        $this->stage2 = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Proposal',
            'position' => 2,
            'probability' => 60,
        ]);
    }

    public function test_crm_leads_renders_kanban_data(): void
    {
        $lead = CrmLead::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage1->id,
            'name' => 'Acme Prospect',
            'company' => 'Acme Corp',
            'estimated_value' => 50000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/crm');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Index')
            ->has('pipelines', 1)
            ->has('allLeads', 1)
            ->where('allLeads.0.name', 'Acme Prospect')
            ->has('leads.data', 1)
            ->has('metrics')
        );
    }

    public function test_crm_leads_move_endpoint_with_transaction_and_audit(): void
    {
        $lead = CrmLead::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage1->id,
            'name' => 'Movable Lead',
            'company' => 'Movable Inc',
            'estimated_value' => 75000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post("/crm/leads/{$lead->id}/move", [
                'stage_id' => $this->stage2->id,
            ]);

        $response->assertRedirect();
        $this->assertEquals($this->stage2->id, $lead->fresh()->stage_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.moved',
            'entity_type' => 'crm_lead',
            'entity_id' => (string) $lead->id,
        ]);
    }

    public function test_crm_deals_renders_kanban_view_with_deal_values(): void
    {
        $deal = CrmDeal::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage1->id,
            'name' => 'Enterprise License Deal',
            'value' => 280000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/crm/deals');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Kanban')
            ->has('stages')
            ->where('stages.0.name', 'Qualified')
            ->where('stages.0.deals_count', 1)
            ->where('stages.0.total_value', 280000)
            ->where('stages.0.deals.0.name', 'Enterprise License Deal')
            ->where('stages.0.deals.0.value', 280000)
        );
    }

    public function test_hrm_dashboard_renders_8_metrics_and_department_sections(): void
    {
        // Add branch and department
        $branchId = DB::table('hr_branches')->insertGetId([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Main Branch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deptId = DB::table('hr_departments')->insertGetId([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'branch_id' => $branchId,
            'name' => 'Engineering',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Active Employee
        $emp1 = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'department_id' => $deptId,
            'branch_id' => $branchId,
            'name' => 'Alice Engineer',
            'email' => 'alice@example.com',
            'employee_number' => 'EMP-001',
            'joined_at' => Carbon::today()->subMonths(6),
            'status' => 'active',
        ]);

        // Terminated Employee
        HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => null,
            'department_id' => $deptId,
            'branch_id' => $branchId,
            'name' => 'Bob Exited',
            'email' => 'bob@example.com',
            'employee_number' => 'EMP-002',
            'joined_at' => Carbon::today()->subYears(1),
            'ended_at' => Carbon::today(),
            'status' => 'terminated',
        ]);

        // Attendance for emp1
        HrAttendance::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_id' => $emp1->id,
            'attendance_date' => Carbon::today(),
            'status' => 'present',
            'clock_in' => Carbon::today()->setTime(9, 0),
        ]);

        // Leave request
        $leaveType = HrLeaveType::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Paid Time Off',
            'days_allowed' => 15,
        ]);

        HrLeaveRequest::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_id' => $emp1->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => Carbon::today(),
            'ends_on' => Carbon::today()->addDays(2),
            'days' => 2,
            'status' => 'approved',
            'reason' => 'Family vacation',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/hrm');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('HRM/Index')
            ->has('stats')
            ->where('stats.total_employees', 2)
            ->where('stats.active_employees', 1)
            ->where('stats.present_today', 1)
            ->where('stats.on_leave_today', 1)
            ->where('stats.total_branches', 1)
            ->where('stats.total_departments', 1)
            ->where('stats.total_promotions', 0)
            ->where('stats.terminations', 1)
            ->has('department_distribution', 1)
            ->where('department_distribution.0.name', 'Engineering')
            ->has('employees_on_leave_today', 1)
            ->where('employees_on_leave_today.0.name', 'Alice Engineer')
        );
    }
}
