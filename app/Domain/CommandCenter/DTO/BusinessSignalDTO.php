<?php

namespace App\Domain\CommandCenter\DTO;

class BusinessSignalDTO
{
    public function __construct(
        public string $id,
        public string $category, // finance, sales, crm, inventory, tasks, communications, helpdesk, operations
        public string $type,
        public string $severity, // info, attention, warning, critical
        public string $title,
        public string $description,
        public mixed $value = null,
        public mixed $threshold = null,
        public string $trend = 'stable', // up, down, stable
        public array $evidence = [],
        public ?string $detectedAt = null,
        public ?string $expiresAt = null
    ) {
        $this->detectedAt = $detectedAt ?? now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'value' => $this->value,
            'threshold' => $this->threshold,
            'trend' => $this->trend,
            'evidence' => $this->evidence,
            'detected_at' => $this->detectedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
