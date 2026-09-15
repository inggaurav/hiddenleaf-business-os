<?php

namespace HiddenLeaf\Hrm\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use HiddenLeaf\Hrm\Models\HrPayrollRun;

class HrPayrollRunSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr_payroll_run_summary';
    }

    public function description(): string
    {
        return 'Get latest payroll run status and totals';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'hrm.manage';
    }

    public function requiredModule(): ?string
    {
        return 'hrm';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $latest = HrPayrollRun::where('workspace_id', $wsId)->latest('period_end')->first();

        if (! $latest) {
            return ToolResult::success([], 'No payroll runs recorded yet in this workspace.', [
                ['type' => 'hrm', 'label' => 'Payroll Runs', 'route' => '/hrm/payroll'],
            ]);
        }

        $paidCount = $latest->payslips()->where('status', 'paid')->count();
        $data = [
            'run_number' => $latest->run_number,
            'period' => $latest->period_start?->toDateString() . ' to ' . $latest->period_end?->toDateString(),
            'status' => $latest->status,
            'employee_count' => $latest->employee_count,
            'total_net' => (float) $latest->total_net,
            'paid_count' => $paidCount,
        ];

        $summary = sprintf('Latest run %s (%s): %d employees, net %0.2f', $latest->run_number, $latest->status, $latest->employee_count, $latest->total_net);

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Payroll Runs', 'route' => '/hrm/payroll'],
        ]);
    }
}
