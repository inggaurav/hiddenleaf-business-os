<?php

namespace App\Domain\MrFox\DTO;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;

class ToolContext
{
    public function __construct(
        public readonly User $user,
        public readonly ?Organization $organization,
        public readonly ?Workspace $workspace,
        public readonly string $conversationId = '',
        public readonly array $metadata = []
    ) {}

    public function getOrganizationId(): ?int
    {
        return $this->organization?->id ?? $this->workspace?->organization_id;
    }

    public function getWorkspaceId(): ?int
    {
        return $this->workspace?->id;
    }
}
