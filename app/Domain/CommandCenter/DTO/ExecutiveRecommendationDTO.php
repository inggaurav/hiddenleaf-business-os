<?php

namespace App\Domain\CommandCenter\DTO;

class ExecutiveRecommendationDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $rationale,
        public string $category,
        public string $severity, // info, attention, warning, critical
        public string $status = 'new', // new, viewed, accepted, dismissed, resolved
        public string $fingerprint = '',
        public array $evidence = [],
        public array $actions = [],
        public ?string $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'rationale' => $this->rationale,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'fingerprint' => $this->fingerprint,
            'evidence' => $this->evidence,
            'actions' => $this->actions,
            'created_at' => $this->createdAt,
        ];
    }
}
