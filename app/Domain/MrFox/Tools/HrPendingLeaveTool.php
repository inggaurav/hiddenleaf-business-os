<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\HrLeaveRequest;

class HrPendingLeaveTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'hr.leave.pending';
    }

    public function description(): string
    {
        return 'Fetch all pending employee leave applications awaiting managerial approval in the workspace.';
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

        $leaves = HrLeaveRequest::query()
            ->where('workspace_id', $wsId)
            ->where('status', 'pending')
            ->with(['employee', 'type'])
            ->take(50)
            ->get();

        $safeData = $leaves->map(fn ($l) => [
            'id' => $l->id,
            'employee_name' => $l->employee?->name,
            'leave_type' => $l->type?->name,
            'starts_on' => optional($l->starts_on)->toDateString(),
            'ends_on' => optional($l->ends_on)->toDateString(),
            'days' => (float) $l->days,
            'status' => $l->status,
        ]);

        $evidence = $leaves->map(fn ($l) => [
            'type' => 'leave',
            'id' => $l->id,
            'label' => "{$l->employee?->name} - {$l->type?->name} ({$l->days} days)",
            'route' => '/hrm/leaves',
        ])->all();

        $summary = sprintf('Identified %d pending leave application(s).', $leaves->count());

        return ToolResult::success($safeData->toArray(), $summary, $evidence);
    }
}
