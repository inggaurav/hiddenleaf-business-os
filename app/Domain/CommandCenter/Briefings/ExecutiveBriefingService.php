<?php

namespace App\Domain\CommandCenter\Briefings;

use App\Domain\CommandCenter\DTO\ExecutiveBriefingDTO;
use App\Domain\CommandCenter\Health\BusinessHealthService;
use App\Domain\CommandCenter\Priorities\BusinessPriorityService;
use App\Domain\CommandCenter\Recommendations\ExecutiveRecommendationService;
use App\Models\AutomationRule;
use App\Models\CommunicationConversation;
use App\Models\CrmLead;
use App\Models\MrFoxActionProposal;
use App\Models\MrFoxMission;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;

class ExecutiveBriefingService
{
    public function __construct(
        private ChangeDetectionService $changeDetector,
        private BusinessPriorityService $priorityService,
        private BusinessHealthService $healthService,
        private ExecutiveRecommendationService $recommendationService
    ) {}

    /**
     * Generate comprehensive, permission-aware executive morning/daily briefing.
     */
    public function generateBriefing(User $user, Workspace $workspace, string $period = 'today'): ExecutiveBriefingDTO
    {
        $wsId = $workspace->id;
        $changes = $this->changeDetector->calculateChanges($workspace, $period);
        $priorities = array_slice($this->priorityService->getPriorities($user, $workspace), 0, 5);
        $health = $this->healthService->evaluateHealth($user, $workspace);
        $recommendations = array_slice($this->recommendationService->getRecommendations($user, $workspace), 0, 5);

        // Summaries
        $totalSales = (float) SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', [0])->sum('total_amount');
        $totalExpenses = (float) PurchaseInvoice::where('workspace_id', $wsId)->whereNotIn('status', [0])->sum('total_amount');
        $overdueReceivables = (float) SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', [0, 3])->where('due_date', '<', today())->sum('total_amount');

        $activeLeads = CrmLead::where('workspace_id', $wsId)->whereNull('converted_at')->count();
        $qualifiedLeads = CrmLead::where('workspace_id', $wsId)->where('status', 'qualified')->count();

        $urgentComms = CommunicationConversation::where('workspace_id', $wsId)->where('priority_score', '>=', 75)->whereIn('status', ['open', 'in_progress'])->count();
        $unreadComms = CommunicationConversation::where('workspace_id', $wsId)->where('unread_count', '>', 0)->count();

        $activeRules = AutomationRule::where('workspace_id', $wsId)->where('enabled', true)->count();
        $runningMissions = MrFoxMission::where('workspace_id', $wsId)->whereIn('status', ['running', 'queued'])->count();
        $waitingApprovals = MrFoxActionProposal::where('workspace_id', $wsId)->where('status', 'pending')->count();

        $headline = sprintf(
            'Executive Briefing for %s: Business Health is %s (%d/100) with %d priority action item(s).',
            $workspace->name,
            strtoupper($health['overall']->status),
            $health['overall']->score,
            count($priorities)
        );

        $topChangesList = [];
        foreach ($changes as $metric => $c) {
            $topChangesList[] = [
                'metric' => str_replace('_', ' ', $metric),
                'current' => $c['current'],
                'previous' => $c['previous'],
                'delta_percentage' => $c['delta_percentage'],
                'direction' => $c['direction'],
            ];
        }

        return new ExecutiveBriefingDTO(
            period: $period,
            summaryHeadline: $headline,
            topChanges: $topChangesList,
            topPriorities: array_map(fn ($p) => $p->toArray(), $priorities),
            financialSnapshot: [
                'total_sales' => $totalSales,
                'total_expenses' => $totalExpenses,
                'net_margin' => $totalSales - $totalExpenses,
                'overdue_receivables' => $overdueReceivables,
            ],
            salesSnapshot: [
                'active_leads' => $activeLeads,
                'qualified_leads' => $qualifiedLeads,
            ],
            communicationsSnapshot: [
                'urgent_conversations' => $urgentComms,
                'unread_threads' => $unreadComms,
            ],
            operationsSnapshot: [
                'active_automation_rules' => $activeRules,
                'running_missions' => $runningMissions,
            ],
            waitingApprovals: [
                'pending_count' => $waitingApprovals,
                'route' => '/command-center/approvals',
            ],
            recommendedActions: array_map(fn ($r) => $r->toArray(), $recommendations)
        );
    }
}
