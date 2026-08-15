<?php

namespace App\Domain\CommandCenter\Priorities;

use App\Domain\CommandCenter\DTO\BusinessPriorityDTO;
use App\Domain\CommandCenter\DTO\BusinessSignalDTO;
use App\Domain\CommandCenter\Signals\SignalDetector;
use App\Models\User;
use App\Models\Workspace;

class BusinessPriorityService
{
    public function __construct(
        private SignalDetector $signalDetector
    ) {}

    /**
     * Generate deterministically ranked executive priorities.
     *
     * @return BusinessPriorityDTO[]
     */
    public function getPriorities(User $user, Workspace $workspace): array
    {
        $signals = $this->signalDetector->detectSignals($user, $workspace);
        $candidates = [];

        foreach ($signals as $signal) {
            $baseScore = match ($signal->severity) {
                'critical' => 1000,
                'warning' => 500,
                'attention' => 200,
                default => 50,
            };

            // Add financial impact weighting if present
            $finWeight = 0;
            $finImpact = null;
            if (is_numeric($signal->value) && in_array($signal->category, ['finance', 'sales'])) {
                $finImpact = (float) $signal->value;
                $finWeight = min(500, (int) round($finImpact / 100));
            }

            // Customer communication urgency bonus
            $commsBonus = $signal->category === 'communications' ? 300 : 0;

            $totalScore = $baseScore + $finWeight + $commsBonus;

            $actionData = $this->resolveActionData($signal);

            $candidates[] = [
                'score' => $totalScore,
                'signal' => $signal,
                'action_data' => $actionData,
                'fin_impact' => $finImpact,
            ];
        }

        // Sort descending by totalScore
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $priorities = [];
        $rank = 1;
        foreach ($candidates as $c) {
            /** @var BusinessSignalDTO $sig */
            $sig = $c['signal'];
            $act = $c['action_data'];

            $priorities[] = new BusinessPriorityDTO(
                rank: $rank++,
                id: "priority_{$sig->id}",
                title: $sig->title,
                whyItMatters: $sig->description,
                category: $sig->category,
                severity: $sig->severity,
                financialImpact: $c['fin_impact'],
                owner: 'Executive Team',
                dueOrAge: 'Immediate',
                recommendedNextAction: $act['recommendation'],
                evidence: $sig->evidence,
                actions: $act['actions']
            );
        }

        return $priorities;
    }

    private function resolveActionData(BusinessSignalDTO $signal): array
    {
        return match ($signal->id) {
            'finance.overdue_receivables' => [
                'recommendation' => 'Review overdue invoices, draft follow-up collection letters, or dispatch automated reminder.',
                'actions' => [
                    ['label' => 'View Invoices', 'type' => 'link', 'route' => '/sales/invoices'],
                    ['label' => 'Draft Collection Reminder', 'type' => 'action', 'action_name' => 'communications.draft_reply'],
                ],
            ],
            'communications.urgent' => [
                'recommendation' => 'Reply to urgent client inquiries to preserve customer satisfaction and deal momentum.',
                'actions' => [
                    ['label' => 'Open Unified Inbox', 'type' => 'link', 'route' => '/communications/inbox'],
                ],
            ],
            'inventory.low_stock' => [
                'recommendation' => 'Generate purchase orders or transfer inventory between warehouses to avoid stockouts.',
                'actions' => [
                    ['label' => 'View Inventory', 'type' => 'link', 'route' => '/productservice'],
                    ['label' => 'Create Restock Task', 'type' => 'action', 'action_name' => 'tasks.create_task'],
                ],
            ],
            'crm.inactive_warm_leads' => [
                'recommendation' => 'Assign dormant qualified leads to sales reps or launch follow-up engagement mission.',
                'actions' => [
                    ['label' => 'Open CRM Leads', 'type' => 'link', 'route' => '/crm/leads'],
                    ['label' => 'Launch Follow-up Mission', 'type' => 'action', 'action_name' => 'missions.create'],
                ],
            ],
            'automation.failed_runs' => [
                'recommendation' => 'Inspect execution stack trace in Automations engine and retry failed step.',
                'actions' => [
                    ['label' => 'Open Automations Log', 'type' => 'link', 'route' => '/automations'],
                ],
            ],
            'mission.waiting_approval' => [
                'recommendation' => 'Review proposed high-risk tool actions in Approval Center.',
                'actions' => [
                    ['label' => 'Open Approvals', 'type' => 'link', 'route' => '/command-center/approvals'],
                ],
            ],
            default => [
                'recommendation' => 'Review relevant operational records and coordinate with department manager.',
                'actions' => [
                    ['label' => 'View Details', 'type' => 'link', 'route' => '/dashboard'],
                ],
            ],
        };
    }
}
