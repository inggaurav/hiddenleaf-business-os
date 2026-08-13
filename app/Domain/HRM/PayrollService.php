<?php

namespace App\Domain\HRM;

use App\Models\HrEmployee;
use App\Models\HrPayslip;
use App\Models\User;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function generate(HrEmployee $employee, string $start, string $end, User $actor): HrPayslip
    {
        return DB::transaction(function () use ($employee, $start, $end, $actor) {
            $components = DB::table('hr_employee_salary_components as assigned')
                ->join('hr_salary_components as component', 'component.id', '=', 'assigned.salary_component_id')
                ->where('assigned.employee_id', $employee->id)->select('component.name', 'component.type', 'component.calculation', 'component.value as default_value', 'assigned.value')->get();
            $earnings = (float) $employee->basic_salary;
            $deductions = 0.0;
            $lines = [['name' => 'Basic Salary', 'type' => 'earning', 'amount' => $earnings]];
            foreach ($components as $component) {
                $value = (float) ($component->value ?? $component->default_value);
                $amount = $component->calculation === 'percentage' ? round((float) $employee->basic_salary * $value / 100, 2) : $value;
                $component->type === 'deduction' ? $deductions += $amount : $earnings += $amount;
                $lines[] = ['name' => $component->name, 'type' => $component->type, 'amount' => $amount];
            }
            $payslip = HrPayslip::create([
                'organization_id' => $employee->organization_id, 'workspace_id' => $employee->workspace_id, 'employee_id' => $employee->id,
                'period_start' => $start, 'period_end' => $end, 'gross_pay' => $earnings, 'deductions' => $deductions,
                'net_pay' => $earnings - $deductions, 'status' => 'draft', 'created_by' => $actor->id,
            ]);
            $payslip->lines()->createMany($lines);
            $this->audit->log($actor->id, $employee->organization_id, $employee->workspace_id, 'payroll.generated', 'hr_payslip', (string) $payslip->id, ['employee_id' => $employee->id, 'period_start' => $start, 'period_end' => $end, 'net_pay' => $payslip->net_pay], critical: true);

            return $payslip->load('lines');
        });
    }
}
