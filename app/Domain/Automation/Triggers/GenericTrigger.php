<?php

namespace App\Domain\Automation\Triggers;

use App\Domain\Automation\Contracts\AutomationTriggerContract;

class GenericTrigger implements AutomationTriggerContract
{
    public function __construct(
        private string $name,
        private string $description,
        private string $module,
        private array $schema = []
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function module(): string
    {
        return $this->module;
    }

    public function schema(): array
    {
        return $this->schema;
    }
}
