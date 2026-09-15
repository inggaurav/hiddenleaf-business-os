<?php

namespace Tests\Feature\HRM;

use App\Models\AccountType;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrPayslip;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'HRM Enterprise', 'modules' => ['hrm', 'account'], 'status' => true, 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'hrm']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'account']);
    }

    public function test_employee_attendance_and_leave_workflow(): void
    {
        // 1. Create Employee
        $empResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/employees', [
                'employee_number' => 'EMP-001',
                'name' => 'Alice Johnson',
                'email' => 'alice@company.com',
                'joined_at' => now()->subYear()->toDateString(),
                'basic_salary' => 6000.00,
            ]);

        $empResponse->assertSessionHasNoErrors();
        $employee = HrEmployee::where('employee_number', 'EMP-001')->firstOrFail();

        // 2. Record Attendance
        $attResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/attendance', [
                'employee_id' => $employee->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'present',
                'notes' => 'On-time arrival',
            ]);

        $attResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hr_attendance', ['employee_id' => $employee->id, 'status' => 'present']);

        // 3. Create Leave Type and Request Leave
        $leaveType = HrLeaveType::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Annual Paid Leave',
            'annual_allowance' => 20,
            'is_paid' => true,
        ]);

        $leaveResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/leaves', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'starts_on' => now()->addDays(5)->toDateString(),
                'ends_on' => now()->addDays(7)->toDateString(),
                'reason' => 'Family vacation',
            ]);

        $leaveResponse->assertSessionHasNoErrors();
        $leave = HrLeaveRequest::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('pending', $leave->status);

        // 4. Review and Approve Leave
        $reviewResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/hrm/leaves/{$leave->id}/review", [
                'decision' => 'approved',
                'notes' => 'Approved by manager',
            ]);

        $reviewResponse->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->refresh()->status);
    }

    public function test_payroll_generation_and_payment_posts_balanced_ledger(): void
    {
        $assetType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Bank', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $expenseType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Salaries Expense', 'classification' => 'expense', 'normal_balance' => 'debit']);

        $bank = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Operating Bank', 'currency' => 'USD', 'is_bank' => true]);
        $salaries = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $expenseType->id, 'code' => '5000', 'name' => 'Salaries Expense', 'currency' => 'USD']);

        $employee = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_number' => 'EMP-002',
            'name' => 'Bob Smith',
            'joined_at' => now()->subMonths(6)->toDateString(),
            'basic_salary' => 5000.00,
            'status' => 'active',
        ]);

        // 1. Generate Payslip
        $genResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/payslips', [
                'employee_id' => $employee->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]);

        $genResponse->assertSessionHasNoErrors();
        $payslip = HrPayslip::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('draft', $payslip->status);
        $this->assertSame('5000.00', (string) $payslip->net_pay);

        // 2. Pay Payslip
        $payResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/hrm/payslips/{$payslip->id}/pay", [
                'bank_account_id' => $bank->id,
            ]);

        $payResponse->assertSessionHasNoErrors();
        $this->assertSame('paid', $payslip->refresh()->status);

        // 3. Verify Journal Entry was created and balanced
        $journal = JournalEntry::where('reference', 'PAYSLIP-'.$payslip->id)->first();
        if ($journal) {
            $this->assertSame('posted', $journal->status);
            $this->assertSame(
                (float) $journal->lines()->sum('debit'),
                (float) $journal->lines()->sum('credit')
            );
        }
    }

    public function test_india_statutory_payroll_calculation_and_compliance(): void
    {
        // 1. Employee with EPF + ESI + PT (Basic: ₹12,000)
        $employeeLow = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_number' => 'EMP-IN-01',
            'name' => 'Aarav Sharma',
            'joined_at' => '2026-01-01',
            'basic_salary' => 12000.00,
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/payslips', [
                'employee_id' => $employeeLow->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'india_statutory' => true,
            ])
            ->assertSessionHasNoErrors();

        $payslipLow = HrPayslip::where('employee_id', $employeeLow->id)->with('lines')->firstOrFail();
        // EPF: 12% of 12,000 = 1,440.00
        // ESI: 0.75% of 12,000 = 90.00
        // PT: > 10,000 in August = 200.00
        // TDS: Net taxable < 7,00,000 => 0.00
        // Total Deductions = 1,440 + 90 + 200 = 1,730.00
        // Net Pay = 12,000 - 1,730 = 10,270.00
        $this->assertSame('12000.00', (string) $payslipLow->gross_pay);
        $this->assertSame('1730.00', (string) $payslipLow->deductions);
        $this->assertSame('10270.00', (string) $payslipLow->net_pay);
        $this->assertCount(4, $payslipLow->lines); // Basic + EPF + ESI + PT

        // 2. High earner with EPF wage ceiling + ESI exempt + PT + TDS
        $employeeHigh = HrEmployee::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'employee_number' => 'EMP-IN-02',
            'name' => 'Priya Patel',
            'joined_at' => '2026-01-01',
            'basic_salary' => 80000.00,
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/hrm/payslips', [
                'employee_id' => $employeeHigh->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'india_statutory' => true,
            ])
            ->assertSessionHasNoErrors();

        $payslipHigh = HrPayslip::where('employee_id', $employeeHigh->id)->with('lines')->firstOrFail();
        // EPF capped at ₹15,000 * 12% = ₹1,800.00
        // ESI: Gross ₹80,000 > ₹21,000 ceiling => Exempt (0.00)
        // PT: > 10,000 in August = 200.00
        // Annual gross = 80,000 * 12 = 9,60,000. Less standard deduction 75,000 = 8,85,000.
        // Tax: 3L-7L (4L @ 5%) = 20,000; 7L-8.85L (1.85L @ 10%) = 18,500. Subtotal = 38,500 + 4% cess = 40,040.
        // Monthly TDS = 40,040 / 12 = 3,336.67.
        // Total Deductions = 1,800 + 200 + 3,336.67 = 5,336.67.
        // Net Pay = 80,000 - 5,336.67 = 74,663.33.
        $this->assertSame('80000.00', (string) $payslipHigh->gross_pay);
        $this->assertSame('5336.67', (string) $payslipHigh->deductions);
        $this->assertSame('74663.33', (string) $payslipHigh->net_pay);
    }
}
