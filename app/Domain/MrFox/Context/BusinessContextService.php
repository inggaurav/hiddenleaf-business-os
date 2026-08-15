<?php

namespace App\Domain\MrFox\Context;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\Settings\SettingsManager;
use App\Models\User;
use App\Models\Workspace;

class BusinessContextService
{
    public function __construct(private SettingsManager $settings) {}

    public function build(User $user, ?Workspace $workspace, string $activePage = 'Dashboard'): array
    {
        $org = $workspace?->organization;
        $modules = $workspace?->enabled_modules ?? ['account', 'hrm', 'crm', 'pos', 'taskly', 'productservice'];
        $currency = $this->settings->get('default_currency', 'USD', $workspace);
        $currencySymbol = $this->settings->get('default_currency_symbol', '$', $workspace);
        $appName = $this->settings->get('app_name', 'HiddenLeaf BusinessOS', $workspace);

        return [
            'app_name' => $appName,
            'active_page' => $activePage,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin'),
            ],
            'organization' => [
                'id' => $org?->id,
                'name' => $org?->name ?? 'Primary Organization',
            ],
            'workspace' => [
                'id' => $workspace?->id,
                'name' => $workspace?->name ?? 'Default Workspace',
            ],
            'enabled_modules' => $modules,
            'currency' => [
                'code' => $currency,
                'symbol' => $currencySymbol,
            ],
        ];
    }

    public function createToolContext(User $user, ?Workspace $workspace, string $conversationId = '', array $metadata = []): ToolContext
    {
        return new ToolContext(
            user: $user,
            organization: $workspace?->organization,
            workspace: $workspace,
            conversationId: $conversationId,
            metadata: $metadata
        );
    }
}
