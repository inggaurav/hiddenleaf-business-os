<?php

namespace App\Domain\CommandCenter\DTO;

class ExecutiveBriefingDTO
{
    public function __construct(
        public string $period, // today, yesterday, last_7_days, custom
        public string $summaryHeadline,
        public array $topChanges = [],
        public array $topPriorities = [],
        public array $financialSnapshot = [],
        public array $salesSnapshot = [],
        public array $communicationsSnapshot = [],
        public array $operationsSnapshot = [],
        public array $waitingApprovals = [],
        public array $recommendedActions = [],
        public ?string $generatedAt = null
    ) {
        $this->generatedAt = $generatedAt ?? now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'summary_headline' => $this->summaryHeadline,
            'top_changes' => $this->topChanges,
            'top_priorities' => $this->topPriorities,
            'financial_snapshot' => $this->financialSnapshot,
            'sales_snapshot' => $this->salesSnapshot,
            'communications_snapshot' => $this->communicationsSnapshot,
            'operations_snapshot' => $this->operationsSnapshot,
            'waiting_approvals' => $this->waitingApprovals,
            'recommended_actions' => $this->recommendedActions,
            'generated_at' => $this->generatedAt,
        ];
    }
}
