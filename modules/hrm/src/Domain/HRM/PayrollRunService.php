<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Domain\HRM\PayrollService;
use App\Models\HrPayslip;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Models\HrEmployee;
use HiddenLeaf\Hrm\Models\HrPayrollRun;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PayrollRunService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createRun(Workspace $ws, string $periodStart, string $periodEnd, User $actor): HrPayrollRun
    {
        $exists = HrPayrollRun::where('workspace_id', $ws->id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->exists();
        abort_if($exists, 422, 'A payroll run already exists for this period.');

        $periodMonth = Carbon::parse($periodStart)->format('Y-m');
        $seq = HrPayrollRun::where('workspace_id', $ws->id)
            ->whereYear('period_start', Carbon::parse($periodStart)->year)
            ->count() + 1;
        $runNumber = sprintf('PAY-%s-%02d', $periodMonth, $seq);

        $run = HrPayrollRun::create([
            'organization_id' => $ws->organization_id,
            'workspace_id' => $ws->id,
            'run_number' => $runNumber,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'employee_count' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor->id,
            $ws->organization_id,
            $ws->id,
            'payroll.run.created',
            'hr_payroll_run',
            (string) $run->id,
            ['run_number' => $runNumber, 'period_start' => $periodStart, 'period_end' => $periodEnd]
        );

        return $run;
    }

    public function addEmployee(HrPayrollRun $run, HrEmployee $employee, User $actor): HrPayslip
    {
        abort_unless($run->status === 'draft', 422, 'Cannot add employees to a non-draft payroll run.');

        $payrollService = app(PayrollService::class);
        $periodStart = $run->period_start instanceof Carbon ? $run->period_start->toDateString() : (string) $run->period_start;
        $periodEnd = $run->period_end instanceof Carbon ? $run->period_end->toDateString() : (string) $run->period_end;

        $payslip = $payrollService->generate($employee, $periodStart, $periodEnd, $actor);
        $payslip->update(['payroll_run_id' => $run->id]);

        $this->recalculateRunTotals($run);

        return $payslip;
    }

    public function addAllActive(HrPayrollRun $run, User $actor): array
    {
        abort_unless($run->status === 'draft', 422, 'Cannot add employees to a non-draft payroll run.');

        $existingEmployeeIds = HrPayslip::where('workspace_id', $run->workspace_id)
            ->where('payroll_run_id', $run->id)
            ->pluck('employee_id')
            ->toArray();

        $activeEmployees = HrEmployee::forWorkspace($run->organization_id, $run->workspace_id)
            ->where('status', 'active')
            ->whereNotIn('id', $existingEmployeeIds)
            ->get();

        $created = [];
        foreach ($activeEmployees as $employee) {
            $created[] = $this->addEmployee($run, $employee, $actor);
        }

        return $created;
    }

    public function approve(HrPayrollRun $run, User $actor): HrPayrollRun
    {
        $run->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
        ]);

        $this->audit->log(
            $actor->id,
            $run->organization_id,
            $run->workspace_id,
            'payroll.run.approved',
            'hr_payroll_run',
            (string) $run->id,
            ['run_number' => $run->run_number],
            critical: true
        );

        return $run;
    }

    public function pay(HrPayrollRun $run, User $actor, ?int $bankAccountId = null): HrPayrollRun
    {
        abort_unless($run->status === 'approved', 422, 'Payroll run must be approved before disbursement.');

        return DB::transaction(function () use ($run, $actor, $bankAccountId): HrPayrollRun {
            $payrollService = app(PayrollService::class);
            $payslips = HrPayslip::where('payroll_run_id', $run->id)->get();

            foreach ($payslips as $payslip) {
                if ($payslip->status !== 'paid') {
                    $payrollService->pay($payslip, $actor, $bankAccountId);
                }
            }

            $run->update(['status' => 'paid']);

            $this->audit->log(
                $actor->id,
                $run->organization_id,
                $run->workspace_id,
                'payroll.run.paid',
                'hr_payroll_run',
                (string) $run->id,
                ['run_number' => $run->run_number, 'total_net' => $run->total_net],
                critical: true
            );

            return $run->refresh();
        });
    }

    public function getSummary(HrPayrollRun $run): array
    {
        $payslips = HrPayslip::where('payroll_run_id', $run->id)->get();

        return [
            'employee_count' => $payslips->count(),
            'total_gross' => (float) $payslips->sum('gross_pay'),
            'total_deductions' => (float) $payslips->sum('deductions'),
            'total_net' => (float) $payslips->sum('net_pay'),
            'paid_count' => $payslips->where('status', 'paid')->count(),
            'draft_count' => $payslips->where('status', 'draft')->count(),
        ];
    }

    private function recalculateRunTotals(HrPayrollRun $run): void
    {
        $payslips = HrPayslip::where('payroll_run_id', $run->id)->get();

        $run->update([
            'employee_count' => $payslips->count(),
            'total_gross' => (float) $payslips->sum('gross_pay'),
            'total_deductions' => (float) $payslips->sum('deductions'),
            'total_net' => (float) $payslips->sum('net_pay'),
        ]);
    }
}
