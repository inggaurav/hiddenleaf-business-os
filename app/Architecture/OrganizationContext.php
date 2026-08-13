<?php

namespace HiddenLeaf\Architecture;

class OrganizationContext
{
    protected ?int $id = null;
    protected ?string $name = null;

    public function set(int $id, string $name): void
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function isSet(): bool
    {
        return $this->id !== null;
    }
}
