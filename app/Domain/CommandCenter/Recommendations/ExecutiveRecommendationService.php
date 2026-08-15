<?php

namespace App\Domain\CommandCenter\Recommendations;

use App\Domain\CommandCenter\DTO\BusinessSignalDTO;
use App\Domain\CommandCenter\DTO\ExecutiveRecommendationDTO;
use App\Domain\CommandCenter\Signals\SignalDetector;
use App\Models\User;
use App\Models\Workspace;

class ExecutiveRecommendationService
{
    public function __construct(
        private SignalDetector $signalDetector
    ) {}

    /**
     * Generate evidence-backed executive recommendations from detected signals.
     *
     * @return ExecutiveRecommendationDTO[]
     */
    public function getRecommendations(User $user, Workspace $workspace): array
    {
        $signals = $this->signalDetector->detectSignals($user, $workspace);
        $recommendations = [];

        foreach ($signals as $sig) {
            $fingerprint = hash('sha256', $sig->id . '_' . (string) $sig->value . '_' . count($sig->evidence));

            $rec = match ($sig->id) {
                'finance.overdue_receivables' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Issue Payment Reminders for {$sig->value} Overdue Receivables",
                    rationale: "Prompt invoice follow-ups improve cash collection cycles and reduce bad debt risk.",
                    category: 'finance',
                    severity: $sig->severity,
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'View Invoices', 'type' => 'link', 'route' => '/sales/invoices'],
                        ['label' => 'Launch Collection Mission', 'type' => 'action', 'action_name' => 'missions.create'],
                    ]
                ),
                'communications.urgent' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Respond to {$sig->value} High-Urgency Customer Inquiries",
                    rationale: "Rapid response time to high-intent conversations directly drives deal closing rates.",
                    category: 'communications',
                    severity: 'critical',
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'Open Inbox', 'type' => 'link', 'route' => '/communications/inbox'],
                    ]
                ),
                'inventory.low_stock' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Generate Restock Orders for {$sig->value} Low/Depleted Products",
                    rationale: "Replenishing critical stock items prevents fulfillment disruptions and lost sales.",
                    category: 'inventory',
                    severity: $sig->severity,
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'View Products', 'type' => 'link', 'route' => '/productservice'],
                        ['label' => 'Create Restock Task', 'type' => 'action', 'action_name' => 'tasks.create_task'],
                    ]
                ),
                'crm.inactive_warm_leads' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Re-engage {$sig->value} Qualified Leads Inactive > 5 Days",
                    rationale: "Reactivating warm prospective deals before they grow cold maximizes marketing ROI.",
                    category: 'crm',
                    severity: 'attention',
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'Open CRM', 'type' => 'link', 'route' => '/crm/leads'],
                        ['label' => 'Start Outreach Mission', 'type' => 'action', 'action_name' => 'missions.create'],
                    ]
                ),
                'automation.failed_runs' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Inspect and Resolve {$sig->value} Automation Rule Failures",
                    rationale: "Fixing broken automations ensures seamless cross-module workflows.",
                    category: 'operations',
                    severity: 'warning',
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'Open Automations Log', 'type' => 'link', 'route' => '/automations'],
                    ]
                ),
                'mission.waiting_approval' => new ExecutiveRecommendationDTO(
                    id: "rec_{$sig->id}",
                    title: "Review and Approve {$sig->value} Pending Mission Action(s)",
                    rationale: "Mr. Fox requires operator authorization before proceeding with governed actions.",
                    category: 'operations',
                    severity: 'attention',
                    fingerprint: $fingerprint,
                    evidence: $sig->evidence,
                    actions: [
                        ['label' => 'Open Approvals Center', 'type' => 'link', 'route' => '/command-center/approvals'],
                    ]
                ),
                default => null,
            };

            if ($rec) {
                $recommendations[] = $rec;
            }
        }

        return $recommendations;
    }
}
