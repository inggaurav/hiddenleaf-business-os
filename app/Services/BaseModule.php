<?php

namespace App\Services;

use HiddenLeaf\Kernel\Contracts\ModuleContract;

abstract class BaseModule implements ModuleContract
{
    protected string $name;

    protected string $alias;

    protected string $version = '1.0.0';

    protected string $description = '';

    protected array $permissions = [];

    protected array $navigation = [];

    protected bool $enabled = true;

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getNavigation(): array
    {
        return $this->navigation;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
