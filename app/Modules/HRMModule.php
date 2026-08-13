<?php

namespace App\Modules;

use App\Services\BaseModule;

class HRMModule extends BaseModule
{
    protected string $name = 'HRM';

    protected string $alias = 'hrm';

    protected string $version = '1.0.0';

    protected string $description = 'Human resource management, employees, attendance tracking, leave requests, payroll processing, and performance appraisals.';

    protected array $permissions = [
        'manage_employees',
        'manage_attendance',
        'manage_leaves',
        'manage_payroll',
    ];

    protected array $navigation = [
        'title' => 'HRM',
        'icon' => 'users',
        'route' => 'hrm.index',
    ];
}
