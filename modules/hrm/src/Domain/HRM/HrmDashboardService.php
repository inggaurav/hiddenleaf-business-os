<?php

namespace HiddenLeaf\Hrm\Domain\HRM;

use App\Domain\HRM\HrmDashboardService as BaseDashboardService;
use App\Models\Workspace;
use HiddenLeaf\Hrm\Models\HrCandidate;
use HiddenLeaf\Hrm\Models\HrDisciplinaryCase;
use HiddenLeaf\Hrm\Models\HrEmployeeOnboarding;
use HiddenLeaf\Hrm\Models\HrPayrollRun;
use HiddenLeaf\Hrm\Models\HrTrainingProgram;

class HrmDashboardService extends BaseDashboardService
{
    public function getExtendedMetrics(Workspace $workspace): array
    {
        $base = $this->getMetrics($workspace);

        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $extended = [
            'candidates_active' => HrCandidate::forWorkspace($orgId, $wsId)->where('status', 'active')->count(),
            'onboarding_in_progress' => HrEmployeeOnboarding::forWorkspace($orgId, $wsId)->where('status', 'in_progress')->count(),
            'training_active' => HrTrainingProgram::forWorkspace($orgId, $wsId)->whereIn('status', ['planned', 'in_progress'])->count(),
            'disciplinary_open' => HrDisciplinaryCase::forWorkspace($orgId, $wsId)->where('status', 'open')->count(),
            'payroll_runs_draft' => HrPayrollRun::forWorkspace($orgId, $wsId)->where('status', 'draft')->count(),
        ];

        return array_merge($base, ['hrm_extended' => $extended]);
    }
}
