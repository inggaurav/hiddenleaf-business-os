<?php

namespace HiddenLeaf\AgenticCrm;

use HiddenLeaf\Kernel\Contracts\ModuleContract;

final class AgenticCrmModule implements ModuleContract
{
    public function getName(): string { return 'Agentic CRM Intelligence'; }
    public function getAlias(): string { return 'agentic-crm'; }
    public function getVersion(): string { return '1.0.0'; }

    public function getPermissions(): array
    {
        return [
            'crm.intelligence.view',
            'crm.intelligence.run',
            'crm.intelligence.review',
            'crm.intelligence.evidence.view',
            'crm.intelligence.tasks.view',
            'crm.intelligence.agents.manage',
        ];
    }

    public function getNavigation(): array
    {
        return [
            ['label' => 'Intelligence', 'route' => 'crm.intelligence', 'permission' => 'crm.intelligence.view'],
            ['label' => 'Review Queue', 'route' => 'crm.intelligence.reviews', 'permission' => 'crm.intelligence.review'],
            ['label' => 'Agents', 'route' => 'crm.intelligence.agents', 'permission' => 'crm.intelligence.agents.manage'],
        ];
    }

    public function isEnabled(): bool { return true; }
}
