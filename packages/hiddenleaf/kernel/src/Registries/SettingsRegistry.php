<?php

namespace HiddenLeaf\Kernel\Registries;

class SettingsRegistry
{
    protected array $defaults = [];

    public function registerDefaults(array $settings): void
    {
        $this->defaults = array_merge($this->defaults, $settings);
    }

    public function resolve(string $key, array $scopes = []): mixed
    {
        // Scope precedence: User -> Workspace -> Organization -> Platform -> Default
        foreach (['user', 'workspace', 'organization', 'platform'] as $scope) {
            if (isset($scopes[$scope][$key])) {
                return $scopes[$scope][$key];
            }
        }

        return $this->defaults[$key] ?? null;
    }
}
