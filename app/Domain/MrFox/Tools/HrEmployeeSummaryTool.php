<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\HrEmployee;

class HrEmployeeSummaryTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr.employee.summary';
    }

    public function description(): string
    {
        return 'Get total workforce headcount, active employees, departments, and payroll summary.';
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

        $employees = HrEmployee::where('workspace_id', $wsId)->get();
        $totalSalary = (float) $employees->sum('basic_salary');

        $data = [
            'total_headcount' => $employees->count(),
            'monthly_payroll_base' => $totalSalary,
        ];

        $summary = sprintf(
            'HR Overview: %d employees | Monthly Payroll Base: $%s',
            $employees->count(),
            number_format($totalSalary, 2)
        );

        return ToolResult::success($data, $summary, [
            ['type' => 'hrm', 'label' => 'Employee Directory', 'route' => '/hrm/employees'],
        ]);
    }
}
