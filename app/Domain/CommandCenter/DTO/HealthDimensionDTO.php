<?php

namespace App\Domain\CommandCenter\DTO;

class HealthDimensionDTO
{
    public function __construct(
        public string $key,
        public string $name,
        public int $score, // 0 - 100
        public string $status, // healthy, watch, warning, critical, unknown
        public string $trend, // up, down, stable
        public array $signals = [],
        public array $evidence = [],
        public ?string $updatedAt = null
    ) {
        $this->updatedAt = $updatedAt ?? now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'score' => $this->score,
            'status' => $this->status,
            'trend' => $this->trend,
            'signals' => $this->signals,
            'evidence' => $this->evidence,
            'updated_at' => $this->updatedAt,
        ];
    }
}
