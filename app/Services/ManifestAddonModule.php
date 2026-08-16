<?php

namespace App\Services;

use App\Models\Addon;
use HiddenLeaf\Kernel\Contracts\AddonExtensionContract;
use HiddenLeaf\Kernel\Contracts\ModuleContract;

class ManifestAddonModule implements AddonExtensionContract, ModuleContract
{
    public function __construct(private readonly Addon $addon) {}

    public function getName(): string
    {
        return $this->addon->name;
    }

    public function getAlias(): string
    {
        return strtolower($this->addon->alias);
    }

    public function getVersion(): string
    {
        return $this->addon->version;
    }

    public function getPermissions(): array
    {
        return $this->arrayAt('permissions');
    }

    public function getNavigation(): array
    {
        return $this->arrayAt('navigation');
    }

    public function isEnabled(): bool
    {
        return in_array(strtolower((string) $this->addon->status), ['installed', 'enabled', 'active'], true);
    }

    public function dependencies(): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $dependency): string => strtolower(trim((string) $dependency)),
            array_filter($this->addon->dependencies ?? [])
        )));
    }

    public function mrFoxTools(): array
    {
        return $this->extensionClasses('mrfox', 'tools');
    }

    public function automationTriggers(): array
    {
        return $this->extensionClasses('automations', 'triggers');
    }

    public function automationActions(): array
    {
        return $this->extensionClasses('automations', 'actions');
    }

    public function searchProviders(): array
    {
        return $this->extensionClasses('search', 'providers');
    }

    public function commandCenterSignals(): array
    {
        return $this->extensionClasses('command_center', 'signals');
    }

    private function arrayAt(string $key): array
    {
        $value = ($this->addon->manifest ?? [])[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    private function extensionClasses(string $section, string $key): array
    {
        $value = ($this->addon->manifest ?? [])['hiddenleaf'][$section][$key] ?? [];
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $class): bool => is_string($class) && $class !== ''
        ));
    }
}
