<?php

namespace App\Domain\CommandCenter\DTO;

class BusinessPriorityDTO
{
    public function __construct(
        public int $rank,
        public string $id,
        public string $title,
        public string $whyItMatters,
        public string $category,
        public string $severity, // info, attention, warning, critical
        public ?float $financialImpact = null,
        public ?string $owner = null,
        public ?string $dueOrAge = null,
        public ?string $recommendedNextAction = null,
        public array $evidence = [],
        public array $actions = []
    ) {}

    public function toArray(): array
    {
        return [
            'rank' => $this->rank,
            'id' => $this->id,
            'title' => $this->title,
            'why_it_matters' => $this->whyItMatters,
            'category' => $this->category,
            'severity' => $this->severity,
            'financial_impact' => $this->financialImpact,
            'owner' => $this->owner,
            'due_or_age' => $this->dueOrAge,
            'recommended_next_action' => $this->recommendedNextAction,
            'evidence' => $this->evidence,
            'actions' => $this->actions,
        ];
    }
}
