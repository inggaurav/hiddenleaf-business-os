<?php

namespace App\Domain\CommandCenter\DTO;

class SearchResultDTO
{
    public function __construct(
        public string $type,
        public string|int $id,
        public string $title,
        public string $subtitle,
        public string $route,
        public float $score = 1.0,
        public array $evidence = [],
        public ?string $badge = null
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => (string) $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'route' => $this->route,
            'score' => $this->score,
            'evidence' => $this->evidence,
            'badge' => $this->badge,
        ];
    }
}
