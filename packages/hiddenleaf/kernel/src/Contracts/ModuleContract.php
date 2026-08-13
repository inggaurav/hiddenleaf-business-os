<?php

namespace HiddenLeaf\Kernel\Contracts;

interface ModuleContract
{
    public function getName(): string;

    public function getAlias(): string;

    public function getVersion(): string;

    public function getPermissions(): array;

    public function getNavigation(): array;

    public function isEnabled(): bool;
}
