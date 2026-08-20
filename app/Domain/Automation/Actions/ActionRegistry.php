<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Models\Workspace;
use App\Services\AddonManager;
use InvalidArgumentException;

class ActionRegistry
{
    /** @var array<string, AutomationActionContract> */
    private array $actions = [];

    /** @var array<string, string> */
    private array $addonOwners = [];

    public function __construct()
    {
        $this->register(new CreateTaskAction);
        $this->register(new CreateCrmNoteAction);
        $this->register(new CreateNotificationAction);
        $this->register(new AssignLeadAction);
        $this->register(new DraftCommunicationAction);
        $this->register(new SendCommunicationAction);
    }

    public function register(AutomationActionContract $action, ?string $addonAlias = null): void
    {
        $this->actions[$action->name()] = $action;
        if ($addonAlias !== null) {
            $this->addonOwners[$action->name()] = strtolower($addonAlias);
        }
    }

    public function get(string $name): AutomationActionContract
    {
        if (! isset($this->actions[$name])) {
            throw new InvalidArgumentException("Automation action '{$name}' is not registered.");
        }

        return $this->actions[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    public function addonOwner(string $name): ?string
    {
        return $this->addonOwners[$name] ?? null;
    }

    public function all(): array
    {
        return $this->actions;
    }

    public function availableFor(Workspace $workspace, bool $superAdmin = false): array
    {
        $addons = app(AddonManager::class);

        return array_filter($this->actions, fn (AutomationActionContract $action): bool => ($owner = $this->addonOwner($action->name())) === null || $addons->canUse($workspace, $owner, $superAdmin)
        );
    }
}
