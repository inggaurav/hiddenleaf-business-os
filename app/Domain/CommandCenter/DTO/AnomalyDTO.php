<?php

namespace App\Domain\CommandCenter\DTO;

class AnomalyDTO
{
    public function __construct(
        public string $id,
        public string $domain,
        public string $title,
        public string $description,
        public mixed $observedValue,
        public mixed $baselineValue,
        public float $deviationPercentage,
        public string $period,
        public string $confidence, // high, medium, low
        public array $evidence = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'title' => $this->title,
            'description' => $this->description,
            'observed_value' => $this->observedValue,
            'baseline_value' => $this->baselineValue,
            'deviation_percentage' => $this->deviationPercentage,
            'period' => $this->period,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
        ];
    }
}
