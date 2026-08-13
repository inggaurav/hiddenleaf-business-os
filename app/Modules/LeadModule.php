<?php

namespace App\Modules;

use App\Services\BaseModule;

class LeadModule extends BaseModule
{
    protected string $name = 'Lead';

    protected string $alias = 'lead';

    protected string $version = '1.0.0';

    protected string $description = 'Lead management, pipelines, deal stages, client interaction tracking, sales analytics, and CRM automation.';

    protected array $permissions = [
        'manage_leads',
        'manage_deals',
        'manage_pipelines',
    ];

    protected array $navigation = [
        'title' => 'CRM & Leads',
        'icon' => 'funnel',
        'route' => 'leads.index',
    ];
}
