<?php

namespace App\Modules;

use App\Services\BaseModule;

class TasklyModule extends BaseModule
{
    protected string $name = 'Taskly';

    protected string $alias = 'taskly';

    protected string $version = '1.0.0';

    protected string $description = 'Project management, task tracking, kanban boards, milestones, timesheets, bug tracking, and team collaboration.';

    protected array $permissions = [
        'manage_projects',
        'create_tasks',
        'manage_milestones',
        'track_timesheets',
    ];

    protected array $navigation = [
        'title' => 'Projects',
        'icon' => 'folder',
        'route' => 'projects.index',
    ];
}
