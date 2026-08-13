<?php

namespace HiddenLeaf\Architecture;

class WorkspaceContext
{
    protected ?int $id = null;
    protected ?int $organizationId = null;
    protected ?string $title = null;

    public function set(int $id, int $organizationId, string $title): void
    {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isSet(): bool
    {
        return $this->id !== null;
    }
}
