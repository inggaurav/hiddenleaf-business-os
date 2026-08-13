<?php

namespace HiddenLeaf\Kernel\Registries;

use HiddenLeaf\Kernel\Contracts\ModuleContract;

class ModuleRegistry
{
    protected array $modules = [];

    public function register(ModuleContract $module): void
    {
        $this->modules[$module->getAlias()] = $module;
    }

    public function get(string $alias): ?ModuleContract
    {
        return $this->modules[$alias] ?? null;
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function enabled(): array
    {
        return array_filter($this->modules, fn (ModuleContract $m) => $m->isEnabled());
    }
}
