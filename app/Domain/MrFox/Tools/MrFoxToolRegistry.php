<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Services\AddonManager;
use App\Services\PermissionService;
use InvalidArgumentException;

class MrFoxToolRegistry
{
    /** @var array<string, MrFoxToolContract> */
    private array $tools = [];

    public function register(MrFoxToolContract $tool): void
    {
        $name = $tool->name();
        if (isset($this->tools[$name])) { throw new InvalidArgumentException("Tool '{$name}' is already registered in MrFoxToolRegistry."); }
        $this->tools[$name] = $tool;
    }

    public function get(string $name): ?MrFoxToolContract { return $this->tools[$name] ?? null; }
    public function has(string $name): bool { return isset($this->tools[$name]); }

    /** @return array<string, MrFoxToolContract> */
    public function all(): array { return $this->tools; }

    /** @return array<string, MrFoxToolContract> */
    public function availableFor(ToolContext $context): array
    {
        $workspace = $context->workspace;
        $user = $context->user;
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');
        $permissionService = app(PermissionService::class);
        $addonManager = app(AddonManager::class);

        return array_filter($this->tools, function (MrFoxToolContract $tool) use ($workspace, $user, $isSuperAdmin, $permissionService, $addonManager): bool {
            $requiredModule = $tool->requiredModule();
            if ($requiredModule !== null && ($workspace === null || ! $addonManager->canUse($workspace, $requiredModule, $isSuperAdmin))) {
                return false;
            }

            $requiredPermission = $tool->requiredPermission();
            if (! $isSuperAdmin && $requiredPermission !== null && $workspace !== null && ! $permissionService->allows($user, $workspace, $requiredPermission)) {
                return false;
            }
            return true;
        });
    }

    public function toOpenAiTools(ToolContext $context): array
    {
        $definitions = [];
        foreach ($this->availableFor($context) as $tool) {
            $definitions[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->inputSchema(),
            ];
        }
        return $definitions;
    }
}
