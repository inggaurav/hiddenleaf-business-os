<?php

namespace App\Domain\Addons;

use RuntimeException;

readonly class AddonManifest
{
    public function __construct(public string $id, public string $alias, public string $name, public string $version, public string $minimumCore, public array $dependencies, public array $permissions = [], public array $settings = [])
    {
        if (! preg_match('/^[a-z][a-z0-9-]{1,63}$/', $alias) || ! preg_match('/^\d+\.\d+\.\d+/', $version)) {
            throw new RuntimeException('Addon manifest identity or version is invalid.');
        }
    }

    public static function from(array $data): self
    {
        foreach (['id', 'alias', 'name', 'version', 'minimum_core', 'dependencies'] as $field) {
            if (! isset($data[$field])) {
                throw new RuntimeException("Addon manifest field {$field} is missing.");
            }
        }

        return new self($data['id'], $data['alias'], $data['name'], $data['version'], $data['minimum_core'], $data['dependencies'], $data['permissions'] ?? [], $data['settings'] ?? []);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'alias' => $this->alias, 'name' => $this->name, 'version' => $this->version, 'minimum_core' => $this->minimumCore, 'dependencies' => $this->dependencies, 'permissions' => $this->permissions, 'settings' => $this->settings];
    }
}
