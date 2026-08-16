<?php

namespace App\Domain\MrFox\Context;

use App\Domain\MrFox\DTO\ToolContext;
use App\Services\AddonManager;
use App\Services\HierarchicalSettingService;
use App\Models\User;
use App\Models\Workspace;

class BusinessContextService
{
    public function __construct(private HierarchicalSettingService $settings, private AddonManager $addons) {}

    public function build(User $user, ?Workspace $workspace, string $activePage = 'Dashboard'): array
    {
        $org = $workspace?->organization;
        $isSuperAdmin = $user->isSuperAdmin();
        $entitled = (array) ($workspace?->organization?->plan?->modules ?? []);
        $modules = $workspace ? array_values(array_filter($entitled, fn ($module) => $this->addons->canUse($workspace, (string) $module, $isSuperAdmin))) : [];
        $resolved = $this->settings->resolved($user, $org, $workspace, false);
        $currency = $resolved['site_currency'] ?? $resolved['defaultCurrency'] ?? 'USD';
        $currencySymbol = $resolved['site_currency_symbol'] ?? $resolved['currencySymbol'] ?? '$';
        $appName = $resolved['titleText'] ?? 'HiddenLeaf BusinessOS';

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
