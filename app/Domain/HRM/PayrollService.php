<?php

namespace App\Domain\HRM;

use App\Domain\Accounting\CommercialAccountingService;
use App\Domain\Accounting\LedgerService;
use App\Models\HrEmployee;
use App\Models\HrPayslip;
use App\Models\LedgerAccount;
use App\Models\User;
use Carbon\Carbon;
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

    public function pay(HrPayslip $payslip, User $actor, ?int $bankAccountId = null): HrPayslip
    {
        return DB::transaction(function () use ($payslip, $actor, $bankAccountId) {
            $locked = HrPayslip::whereKey($payslip->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status !== 'paid', 422, 'Payslip is already marked as paid.');

            $locked->update(['status' => 'paid']);

            // Post double-entry accounting entry if LedgerService is configured for the workspace
            try {
                $ledger = app(LedgerService::class);
                $commercial = app(CommercialAccountingService::class);
                $accounts = (new \ReflectionClass($commercial))->getMethod('systemAccounts');
                $accounts->setAccessible(true);
                $sysAccounts = $accounts->invoke($commercial, $locked->organization_id, $locked->workspace_id);

                $expenseAccount = $sysAccounts['purchase_expense'] ?? null;
                $bankAccount = $bankAccountId
                    ? LedgerAccount::forWorkspace($locked->organization_id, $locked->workspace_id)->find($bankAccountId)
                    : ($sysAccounts['bank'] ?? LedgerAccount::forWorkspace($locked->organization_id, $locked->workspace_id)->where('is_bank', true)->first());

                if ($expenseAccount && $bankAccount && (float) $locked->net_pay > 0) {
                    $entry = $ledger->createEntry($locked->organization_id, $locked->workspace_id, [
                        'entry_date' => $locked->period_end ? Carbon::parse($locked->period_end)->toDateString() : now()->toDateString(),
                        'reference' => 'PAYSLIP-'.$locked->id,
                        'description' => 'Salary payment for employee #'.$locked->employee_id,
                        'lines' => [
                            ['account_id' => $expenseAccount->id, 'debit' => $locked->net_pay, 'credit' => 0],
                            ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $locked->net_pay],
                        ],
                    ], $actor);
                    $ledger->post($entry, $actor);
                }
            } catch (\Throwable $e) {
                // If accounting is not seeded or active, log and proceed with payroll status
            }

            $this->audit->log(
                $actor->id,
                $locked->organization_id,
                $locked->workspace_id,
                'payroll.paid',
                'hr_payslip',
                (string) $locked->id,
                ['net_pay' => $locked->net_pay, 'employee_id' => $locked->employee_id],
                critical: true
            );

            return $locked->refresh();
        });
    }
}
