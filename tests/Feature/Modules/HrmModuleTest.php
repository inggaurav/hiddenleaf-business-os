<?php

namespace Tests\Feature\Modules;

use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrPayslip;
use App\Models\HrSalaryComponent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrmModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'HR Plan', 'modules' => ['hrm'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'hrm']);
    }

    public function test_employee_attendance_leave_and_payroll_lifecycle(): void
    {
        Storage::fake('local');
        $this->request()->post('/hrm/employees', ['employee_number' => 'EMP-001', 'name' => 'Ada Engineer', 'email' => 'ada@example.test', 'joined_at' => '2026-01-01', 'basic_salary' => 5000])->assertSessionHasNoErrors();
        $employee = HrEmployee::sole();
        $this->request()->post('/hrm/attendance', ['employee_id' => $employee->id, 'attendance_date' => '2026-08-14', 'clock_in' => '2026-08-14 09:00:00', 'clock_out' => '2026-08-14 17:00:00', 'status' => 'present'])->assertSessionHasNoErrors();
        $this->request()->post('/hrm/leave-types', ['name' => 'Annual', 'annual_allowance' => 20, 'is_paid' => true])->assertSessionHasNoErrors();
        $leaveType = HrLeaveType::sole();
        $this->request()->post('/hrm/leaves', ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-03', 'reason' => 'Family trip'])->assertSessionHasNoErrors();
        $leave = HrLeaveRequest::sole();
        $this->assertSame('3.00', $leave->days);
        $this->request()->post("/hrm/leaves/{$leave->id}/review", ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->refresh()->status);

        $this->request()->post('/hrm/salary-components', ['name' => 'Housing', 'type' => 'earning', 'calculation' => 'percentage', 'value' => 10, 'is_taxable' => true])->assertSessionHasNoErrors();
        $housing = HrSalaryComponent::sole();
        $this->request()->post('/hrm/salary-components/assign', ['employee_id' => $employee->id, 'component_id' => $housing->id])->assertSessionHasNoErrors();
        $this->request()->post('/hrm/salary-components', ['name' => 'Insurance', 'type' => 'deduction', 'calculation' => 'fixed', 'value' => 200, 'is_taxable' => false])->assertSessionHasNoErrors();
        $insurance = HrSalaryComponent::where('name', 'Insurance')->firstOrFail();
        $this->request()->post('/hrm/salary-components/assign', ['employee_id' => $employee->id, 'component_id' => $insurance->id])->assertSessionHasNoErrors();
        $this->request()->post('/hrm/payslips', ['employee_id' => $employee->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'])->assertSessionHasNoErrors();

        $payslip = HrPayslip::sole();
        $this->assertSame('5500.00', $payslip->gross_pay);
        $this->assertSame('200.00', $payslip->deductions);
        $this->assertSame('5300.00', $payslip->net_pay);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll.generated', 'entity_id' => (string) $payslip->id]);
        $this->request()->post('/hrm/appraisals', ['employee_id' => $employee->id, 'review_date' => '2026-08-31', 'rating' => 5, 'feedback' => 'Excellent'])->assertSessionHasNoErrors();
        $this->request()->post('/hrm/documents', ['employee_id' => $employee->id, 'name' => 'Employment Contract.pdf', 'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $document = DB::table('hr_documents')->first();
        $this->request()->get("/hrm/documents/{$document->id}/download")->assertOk();
    }

    public function test_leave_allowance_and_cross_tenant_review_are_rejected(): void
    {
        $employee = HrEmployee::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'employee_number' => 'E-2', 'name' => 'Employee', 'joined_at' => '2026-01-01', 'basic_salary' => 0, 'status' => 'active']);
        $type = HrLeaveType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Short', 'annual_allowance' => 1, 'is_paid' => true]);
        $this->request()->post('/hrm/leaves', ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02'])->assertStatus(422);

        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignEmployee = HrEmployee::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'employee_number' => 'X', 'name' => 'Foreign', 'joined_at' => '2026-01-01', 'basic_salary' => 0, 'status' => 'active']);
        $foreignType = HrLeaveType::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'name' => 'Leave', 'annual_allowance' => 10]);
        $foreignLeave = HrLeaveRequest::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'employee_id' => $foreignEmployee->id, 'leave_type_id' => $foreignType->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-01', 'days' => 1, 'status' => 'pending']);
        $this->request()->post("/hrm/leaves/{$foreignLeave->id}/review", ['decision' => 'approved'])->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}
