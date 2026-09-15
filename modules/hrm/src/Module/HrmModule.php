<?php

namespace HiddenLeaf\Hrm\Module;

use HiddenLeaf\Kernel\Contracts\ModuleContract;

class HrmModule implements ModuleContract
{
    public function getName(): string
    {
        return 'HRM — Human Resource Management';
    }

    public function getAlias(): string
    {
        return 'hrm';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getPermissions(): array
    {
        return [
            'hrm.view',
            'hrm.manage',
        ];
    }

    public function getNavigation(): array
    {
        return [
            [
                'label' => 'HRM',
                'route' => '/hrm',
                'icon' => 'users',
                'children' => [
                    ['label' => 'Dashboard', 'route' => '/hrm'],
                    ['label' => 'Employees', 'route' => '/hrm/employees'],
                    ['label' => 'Recruitment', 'route' => '/hrm/recruitment'],
                    ['label' => 'Onboarding', 'route' => '/hrm/onboarding'],
                    ['label' => 'Attendance', 'route' => '/hrm/attendance'],
                    ['label' => 'Leave', 'route' => '/hrm/leave'],
                    ['label' => 'Timesheets', 'route' => '/hrm/timesheets'],
                    ['label' => 'Payroll', 'route' => '/hrm/payroll'],
                    ['label' => 'Training', 'route' => '/hrm/training'],
                    ['label' => 'Appraisals', 'route' => '/hrm/appraisals'],
                    ['label' => 'Disciplinary', 'route' => '/hrm/disciplinary'],
                    ['label' => 'Exit', 'route' => '/hrm/exit'],
                ],
            ],
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
