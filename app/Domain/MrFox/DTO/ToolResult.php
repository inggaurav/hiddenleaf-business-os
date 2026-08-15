<?php

namespace App\Domain\MrFox\DTO;

class ToolResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly string $summary,
        public readonly array $evidence = [],
        public readonly ?string $error = null,
        public readonly bool $requiresApproval = false,
        public readonly ?int $proposalId = null
    ) {}

    public static function success(mixed $data, string $summary, array $evidence = []): self
    {
        return new self(
            success: true,
            data: $data,
            summary: $summary,
            evidence: $evidence
        );
    }

    public static function error(string $error, mixed $data = null): self
    {
        return new self(
            success: false,
            data: $data,
            summary: "Error: {$error}",
            error: $error
        );
    }

    public static function proposed(int $proposalId, string $summary, array $evidence = []): self
    {
        return new self(
            success: true,
            data: ['proposal_id' => $proposalId, 'status' => 'pending'],
            summary: $summary,
            evidence: $evidence,
            requiresApproval: true,
            proposalId: $proposalId
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'summary' => $this->summary,
            'evidence' => $this->evidence,
            'error' => $this->error,
            'requires_approval' => $this->requiresApproval,
            'proposal_id' => $this->proposalId,
        ];
    }
}
