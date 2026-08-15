<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Models\UserActiveModule;
use App\Services\PermissionService;
use InvalidArgumentException;

class MrFoxToolRegistry
{
    /** @var array<string, MrFoxToolContract> */
    private array $tools = [];

    public function register(MrFoxToolContract $tool): void
    {
        $name = $tool->name();
        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool '{$name}' is already registered in MrFoxToolRegistry.");
        }

        $this->tools[$name] = $tool;
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
     * Return tools strictly accessible given the current user's permissions and workspace enabled modules.
     *
     * @return array<string, MrFoxToolContract>
     */
    public function availableFor(ToolContext $context): array
    {
        $workspace = $context->workspace;
        $user = $context->user;

        // Resolve active workspace modules from DB if workspace exists
        $activeModules = [];
        if ($workspace) {
            $activeModules = UserActiveModule::where('workspace_id', $workspace->id)
                ->pluck('module_name')
                ->map(fn ($m) => strtolower($m))
                ->all();
        }

        // Fallback default core modules if no explicit rows exist yet
        if (empty($activeModules)) {
            $activeModules = ['account', 'hrm', 'crm', 'pos', 'taskly', 'productservice', 'lead'];
        }

        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');
        $permissionService = app(PermissionService::class);

        return array_filter($this->tools, function (MrFoxToolContract $tool) use ($workspace, $user, $activeModules, $isSuperAdmin, $permissionService) {
            // 1. Module Entitlement Check
            $reqModule = $tool->requiredModule();
            if ($reqModule !== null) {
                if (! in_array(strtolower($reqModule), $activeModules, true)) {
                    return false;
                }
            }

            // 2. Server-side RBAC Permission Check
            $reqPermission = $tool->requiredPermission();
            if (! $isSuperAdmin && $reqPermission !== null && $workspace !== null) {
                if (! $permissionService->allows($user, $workspace, $reqPermission)) {
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
