<?php

namespace HiddenLeaf\Architecture;

class ActorContext
{
    protected ?int $userId = null;

    protected ?string $role = null;

    protected array $permissions = [];

    public function set(int $userId, string $role, array $permissions): void
    {
        $this->userId = $userId;
        $this->role = $role;
        $this->permissions = $permissions;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }
}
