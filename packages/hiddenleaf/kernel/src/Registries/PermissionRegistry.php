<?php

namespace HiddenLeaf\Kernel\Registries;

class PermissionRegistry
{
    protected array $permissions = [];

    public function register(string $module, array $rules): void
    {
        foreach ($rules as $resource => $actions) {
            foreach ($actions as $action) {
                $permissionKey = "{$module}.{$resource}.{$action}";
                $this->permissions[$permissionKey] = [
                    'module' => $module,
                    'resource' => $resource,
                    'action' => $action,
                    'permission' => $permissionKey,
                ];
            }
        }
    }

    public function all(): array
    {
        return $this->permissions;
    }

    public function has(string $permissionKey): bool
    {
        return isset($this->permissions[$permissionKey]);
    }
}
