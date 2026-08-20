<?php

namespace App\Domain\CommandCenter\Anomalies;

use App\Domain\CommandCenter\DTO\AnomalyDTO;
use App\Models\AutomationRun;
use App\Models\CommunicationMessage;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

class AnomalyDetectionEngine
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Detect deterministic operational and financial anomalies.
     *
     * @return AnomalyDTO[]
     */
    public function detectAnomalies(User $user, Workspace $workspace): array
    {
        $anomalies = [];
        $wsId = $workspace->id;
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');

        // 1. Financial: Sudden Surge in Overdue Invoices
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'account.manage')) {
            $overdueInvoices = SalesInvoice::where('workspace_id', $wsId)
                ->whereNotIn('status', [0, 3])
                ->where('due_date', '<', today())
                ->get();

            $overdueSum = (float) $overdueInvoices->sum('total_amount');
            $totalReceivables = (float) SalesInvoice::where('workspace_id', $wsId)
                ->whereNotIn('status', [0, 3])
                ->sum('total_amount');

            if ($totalReceivables > 0 && ($overdueSum / $totalReceivables) > 0.40) {
                $deviation = round(($overdueSum / $totalReceivables) * 100, 1);
                $anomalies[] = new AnomalyDTO(
                    id: 'anomaly.finance.overdue_spike',
                    domain: 'finance',
                    title: 'High Overdue Receivables Concentration',
                    description: "{$deviation}% of total open receivables are currently past due date.",
                    observedValue: $overdueSum,
                    baselineValue: $totalReceivables * 0.15,
                    deviationPercentage: $deviation,
                    period: 'current',
                    confidence: 'high',
                    evidence: $overdueInvoices->take(3)->map(fn ($i) => [
                        'type' => 'invoice',
                        'id' => $i->id,
                        'label' => "Invoice #{$i->invoice_id}: $".number_format((float) $i->total_amount, 2),
                    ])->all()
                );
            }
        }

        // 2. Communications: Spike in Outbound Delivery Failures
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'communications.view')) {
            $failedSends = CommunicationMessage::where('workspace_id', $wsId)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(48))
                ->count();

            if ($failedSends >= 2) {
                $anomalies[] = new AnomalyDTO(
                    id: 'anomaly.comms.failure_spike',
                    domain: 'communications',
                    title: 'Repeated Outbound Message Delivery Failures',
                    description: "{$failedSends} messages failed delivery across channels in the last 48 hours.",
                    observedValue: $failedSends,
                    baselineValue: 0,
                    deviationPercentage: 100.0,
                    period: 'last_48_hours',
                    confidence: 'high',
                    evidence: []
                );
            }
        }

        // 3. Operations: Automation Failure Rate Spike
        $totalRuns = AutomationRun::where('workspace_id', $wsId)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $failedRuns = AutomationRun::where('workspace_id', $wsId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($totalRuns >= 3 && ($failedRuns / $totalRuns) > 0.33) {
            $rate = round(($failedRuns / $totalRuns) * 100, 1);
            $anomalies[] = new AnomalyDTO(
                id: 'anomaly.operations.automation_failures',
                domain: 'operations',
                title: 'High Automation Failure Rate',
                description: "{$failedRuns} of {$totalRuns} ({$rate}%) automation runs failed in the last 24 hours.",
                observedValue: $failedRuns,
                baselineValue: 0,
                deviationPercentage: $rate,
                period: 'last_24_hours',
                confidence: 'high',
                evidence: []
            );
        }

        return $anomalies;
    }
}
