<?php

namespace HiddenLeaf\Hrm\Providers;

use HiddenLeaf\Hrm\Domain\HRM\DisciplinaryService;
use HiddenLeaf\Hrm\Domain\HRM\ExitService;
use HiddenLeaf\Hrm\Domain\HRM\HrmDashboardService;
use HiddenLeaf\Hrm\Domain\HRM\OnboardingService;
use HiddenLeaf\Hrm\Domain\HRM\PayrollRunService;
use HiddenLeaf\Hrm\Domain\HRM\PayrollService;
use HiddenLeaf\Hrm\Domain\HRM\RecruitmentService;
use HiddenLeaf\Hrm\Domain\HRM\TimesheetService;
use HiddenLeaf\Hrm\Domain\HRM\TrainingService;
use Illuminate\Support\ServiceProvider;

class HrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind all HRM domain services into the container
        $this->app->singleton(HrmDashboardService::class);
        $this->app->singleton(PayrollService::class);
        $this->app->singleton(PayrollRunService::class);
        $this->app->singleton(RecruitmentService::class);
        $this->app->singleton(OnboardingService::class);
        $this->app->singleton(TrainingService::class);
        $this->app->singleton(TimesheetService::class);
        $this->app->singleton(DisciplinaryService::class);
        $this->app->singleton(ExitService::class);
    }

    public function boot(): void
    {
        // Migrations - run on module activate
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/hrm.php');

        // Register MrFox tools if the MrFox tool registry is available
        if ($this->app->bound(\App\Domain\MrFox\Tools\MrFoxToolRegistry::class)) {
            $registry = $this->app->make(\App\Domain\MrFox\Tools\MrFoxToolRegistry::class);
            foreach ($this->mrfoxTools() as $toolClass) {
                if (class_exists($toolClass)) {
                    $tool = $this->app->make($toolClass);
                    if (! $registry->has($tool->name())) {
                        $registry->register($tool);
                    }
                }
            }
        }
    }

    private function mrfoxTools(): array
    {
        return [
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrAttendanceSummaryTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrEmployeeSummaryTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrPendingLeaveTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrRecruitmentPipelineTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrTimesheetSummaryTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrTrainingSummaryTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrExitStatusTool::class,
            \HiddenLeaf\Hrm\Domain\MrFox\Tools\HrPayrollRunSummaryTool::class,
        ];
    }
}
