<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;

class MrFoxToolRegistry
{
    /** @var array<string, MrFoxToolContract> */
    private array $tools = [];

    public function register(MrFoxToolContract $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?MrFoxToolContract
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, MrFoxToolContract>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Return tools accessible given the current user's permissions and workspace enabled modules.
     *
     * @return array<string, MrFoxToolContract>
     */
    public function availableFor(ToolContext $context): array
    {
        $enabledModules = $context->workspace?->enabled_modules ?? [
            'account', 'hrm', 'crm', 'pos', 'taskly', 'productservice',
        ];
        $isSuperAdmin = method_exists($context->user, 'isSuperAdmin') ? $context->user->isSuperAdmin() : ($context->user->role === 'super_admin');
        $userPermissions = $context->user->permissions ?? [];

        return array_filter($this->tools, function (MrFoxToolContract $tool) use ($enabledModules, $isSuperAdmin, $userPermissions) {
            // Check module entitlement
            if ($tool->requiredModule() !== null && ! in_array(strtolower($tool->requiredModule()), array_map('strtolower', $enabledModules), true)) {
                return false;
            }

            // Check RBAC permission
            if (! $isSuperAdmin && $tool->requiredPermission() !== null) {
                if (! in_array($tool->requiredPermission(), $userPermissions, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Convert available tools to OpenAI Function Calling format.
     */
    public function toOpenAiTools(ToolContext $context): array
    {
        $available = $this->availableFor($context);
        $definitions = [];

        foreach ($available as $tool) {
            $definitions[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->inputSchema(),
            ];
        }

        return $definitions;
    }
}
