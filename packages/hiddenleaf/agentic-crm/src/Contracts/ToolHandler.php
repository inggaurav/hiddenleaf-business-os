<?php

namespace HiddenLeaf\AgenticCrm\Contracts;

interface ToolHandler
{
    public function name(): string;

    public function execute(array $input, array $context = []): array;
}
