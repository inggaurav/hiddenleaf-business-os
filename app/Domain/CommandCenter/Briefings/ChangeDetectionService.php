<?php

namespace App\Domain\CommandCenter\Briefings;

use App\Models\AutomationRun;
use App\Models\CommunicationConversation;
use App\Models\CrmLead;
use App\Models\SalesInvoice;
use App\Models\Workspace;
use Carbon\Carbon;

class ChangeDetectionService
{
    /**
     * Calculate deterministic period-over-period operational and financial deltas.
     *
     * @return array<string, array{current: mixed, previous: mixed, delta_percentage: float, direction: string}>
     */
    public function calculateChanges(Workspace $workspace, string $period = 'today'): array
    {
        $wsId = $workspace->id;

        [$startCurrent, $endCurrent, $startPrev, $endPrev] = match ($period) {
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
                Carbon::yesterday()->subDay()->startOfDay(),
                Carbon::yesterday()->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                Carbon::now()->subDays(7)->startOfDay(),
                Carbon::now()->endOfDay(),
                Carbon::now()->subDays(14)->startOfDay(),
                Carbon::now()->subDays(7)->startOfDay(),
            ],
            default => [ // 'today'
                Carbon::today()->startOfDay(),
                Carbon::now(),
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->copy()->setTime(Carbon::now()->hour, Carbon::now()->minute),
            ],
        };

        // 1. Sales Volume Delta
        $salesCurr = (float) SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', [0])->whereBetween('created_at', [$startCurrent, $endCurrent])->sum('total_amount');
        $salesPrev = (float) SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', [0])->whereBetween('created_at', [$startPrev, $endPrev])->sum('total_amount');
        $salesDelta = $salesPrev > 0 ? round((($salesCurr - $salesPrev) / $salesPrev) * 100, 1) : ($salesCurr > 0 ? 100.0 : 0.0);

        // 2. New CRM Leads Delta
        $leadsCurr = CrmLead::where('workspace_id', $wsId)->whereBetween('created_at', [$startCurrent, $endCurrent])->count();
        $leadsPrev = CrmLead::where('workspace_id', $wsId)->whereBetween('created_at', [$startPrev, $endPrev])->count();
        $leadsDelta = $leadsPrev > 0 ? round((($leadsCurr - $leadsPrev) / $leadsPrev) * 100, 1) : ($leadsCurr > 0 ? 100.0 : 0.0);

        // 3. Urgent Communications Delta
        $urgentCurr = CommunicationConversation::where('workspace_id', $wsId)->where('priority_score', '>=', 75)->whereBetween('created_at', [$startCurrent, $endCurrent])->count();
        $urgentPrev = CommunicationConversation::where('workspace_id', $wsId)->where('priority_score', '>=', 75)->whereBetween('created_at', [$startPrev, $endPrev])->count();

        // 4. Failed Automations Delta
        $failsCurr = AutomationRun::where('workspace_id', $wsId)->where('status', 'failed')->whereBetween('created_at', [$startCurrent, $endCurrent])->count();
        $failsPrev = AutomationRun::where('workspace_id', $wsId)->where('status', 'failed')->whereBetween('created_at', [$startPrev, $endPrev])->count();

        return [
            'sales_volume' => [
                'current' => $salesCurr,
                'previous' => $salesPrev,
                'delta_percentage' => $salesDelta,
                'direction' => $salesCurr >= $salesPrev ? 'up' : 'down',
            ],
            'new_leads' => [
                'current' => $leadsCurr,
                'previous' => $leadsPrev,
                'delta_percentage' => $leadsDelta,
                'direction' => $leadsCurr >= $leadsPrev ? 'up' : 'down',
            ],
            'urgent_communications' => [
                'current' => $urgentCurr,
                'previous' => $urgentPrev,
                'delta_percentage' => $urgentPrev > 0 ? round((($urgentCurr - $urgentPrev) / $urgentPrev) * 100, 1) : ($urgentCurr > 0 ? 100.0 : 0.0),
                'direction' => $urgentCurr >= $urgentPrev ? 'up' : 'down',
            ],
            'automation_failures' => [
                'current' => $failsCurr,
                'previous' => $failsPrev,
                'delta_percentage' => $failsPrev > 0 ? round((($failsCurr - $failsPrev) / $failsPrev) * 100, 1) : ($failsCurr > 0 ? 100.0 : 0.0),
                'direction' => $failsCurr >= $failsPrev ? 'up' : 'down',
            ],
        ];
    }
}
