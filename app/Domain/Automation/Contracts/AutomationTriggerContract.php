<?php

namespace App\Domain\Automation\Contracts;

interface AutomationTriggerContract
{
    public function name(): string;

    public function description(): string;

    public function module(): string;

    public function schema(): array;
}
