<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use InvalidArgumentException;

class ActionRegistry
{
    /** @var array<string, AutomationActionContract> */
    private array $actions = [];

    public function __construct()
    {
        $this->register(new CreateTaskAction());
        $this->register(new CreateCrmNoteAction());
        $this->register(new CreateNotificationAction());
        $this->register(new AssignLeadAction());
        $this->register(new DraftCommunicationAction());
        $this->register(new SendCommunicationAction());
    }

    public function register(AutomationActionContract $action): void
    {
        $this->actions[$action->name()] = $action;
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

    public function all(): array
    {
        return $this->actions;
    }
}
