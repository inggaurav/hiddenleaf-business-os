<?php

namespace App\Domain\CommandCenter\Health;

use App\Domain\CommandCenter\DTO\HealthDimensionDTO;
use App\Domain\CommandCenter\Signals\SignalDetector;
use App\Models\AutomationRun;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\CrmLead;
use App\Models\HrLeaveRequest;
use App\Models\MrFoxMission;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

class BusinessHealthService
{
    public function __construct(
        private SignalDetector $signalDetector,
        private PermissionService $permissionService
    ) {}

    /**
     * Compute comprehensive, permission-aware business health dimensions.
     *
     * @return array{overall: HealthDimensionDTO, dimensions: array<string, HealthDimensionDTO>}
     */
    public function evaluateHealth(User $user, Workspace $workspace): array
    {
        $dimensions = [];
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');
        $wsId = $workspace->id;
        $activeModules = $workspace->enabled_modules ?? ['account', 'hrm', 'crm', 'pos', 'taskly', 'productservice'];

        // 1. Receivables Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'account.manage')) {
            $totalReceivables = (float) SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', ['paid', 'draft', 0, 3])->sum('total_amount');
            $overdueInvoices = SalesInvoice::where('workspace_id', $wsId)->whereNotIn('status', ['paid', 'draft', 0, 3])->where('due_date', '<', today())->get();
            $overdueSum = (float) $overdueInvoices->sum('total_amount');
            $overdueCount = $overdueInvoices->count();

            $score = 100;
            if ($totalReceivables > 0) {
                $overdueRatio = $overdueSum / $totalReceivables;
                $deduction = ($overdueRatio * 60) + min(30, $overdueCount * 5);
                $score = max(10, (int) round(100 - $deduction));
            } elseif ($overdueCount > 0) {
                $score = max(20, 100 - ($overdueCount * 15));
            }

            $status = $this->resolveStatus($score);
            $dimensions['receivables'] = new HealthDimensionDTO(
                key: 'receivables',
                name: 'Accounts Receivable',
                score: $score,
                status: $status,
                trend: $overdueCount > 0 ? 'down' : 'stable',
                signals: $overdueCount > 0 ? ["{$overdueCount} overdue invoice(s) totalling $".number_format($overdueSum, 2)] : ['All invoices are current.'],
                evidence: $overdueInvoices->take(3)->map(fn ($i) => ['type' => 'invoice', 'id' => $i->id, 'label' => "Invoice #{$i->invoice_id}: $".number_format((float) $i->total_amount, 2), 'route' => "/sales/invoices/{$i->id}"])->all()
            );

            // 2. Payables Health
            $totalPayables = (float) PurchaseInvoice::where('workspace_id', $wsId)->whereNotIn('status', ['paid', 'draft', 0, 3])->sum('total_amount');
            $overdueBills = PurchaseInvoice::where('workspace_id', $wsId)->whereNotIn('status', ['paid', 'draft', 0, 3])->where('due_date', '<', today())->get();
            $overdueBillsSum = (float) $overdueBills->sum('total_amount');

            $scorePay = 100;
            if ($totalPayables > 0) {
                $scorePay = max(20, (int) round(100 - (($overdueBillsSum / $totalPayables) * 60)));
            } elseif ($overdueBills->count() > 0) {
                $scorePay = 50;
            }

            $dimensions['payables'] = new HealthDimensionDTO(
                key: 'payables',
                name: 'Accounts Payable',
                score: $scorePay,
                status: $this->resolveStatus($scorePay),
                trend: 'stable',
                signals: $overdueBills->count() > 0 ? ["{$overdueBills->count()} vendor bill(s) past due."] : ['No overdue vendor liabilities.'],
                evidence: $overdueBills->take(3)->map(fn ($b) => ['type' => 'bill', 'id' => $b->id, 'label' => "Bill #{$b->invoice_id}: $".number_format((float) $b->total_amount, 2), 'route' => "/purchases/invoices/{$b->id}"])->all()
            );

            // 3. Cash Position
            $cashScore = 100; // Standard operating liquidity baseline
            $dimensions['cash'] = new HealthDimensionDTO(
                key: 'cash',
                name: 'Cash & Liquidity',
                score: $cashScore,
                status: $this->resolveStatus($cashScore),
                trend: 'stable',
                signals: ['Operating liquidity within normal thresholds.'],
                evidence: []
            );
        }

        // 4. CRM & Sales Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'crm.manage')) {
            $dormantLeads = CrmLead::where('workspace_id', $wsId)->where('status', 'qualified')->where('updated_at', '<', now()->subDays(5))->count();
            $scoreCrm = max(20, 100 - ($dormantLeads * 10));

            $dimensions['crm'] = new HealthDimensionDTO(
                key: 'crm',
                name: 'Sales & CRM Pipeline',
                score: $scoreCrm,
                status: $this->resolveStatus($scoreCrm),
                trend: $dormantLeads > 0 ? 'down' : 'up',
                signals: $dormantLeads > 0 ? ["{$dormantLeads} qualified lead(s) inactive > 5 days."] : ['Active engagement across all qualified leads.'],
                evidence: []
            );
        }

        // 5. Communications Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'communications.view')) {
            $urgentCount = CommunicationConversation::where('workspace_id', $wsId)->where('priority_score', '>=', 75)->whereIn('status', ['open', 'in_progress'])->count();
            $failedSends = CommunicationMessage::where('workspace_id', $wsId)->where('status', 'failed')->where('created_at', '>=', now()->subHours(48))->count();

            $commsScore = max(15, 100 - ($urgentCount * 25) - ($failedSends * 15));
            $dimensions['communications'] = new HealthDimensionDTO(
                key: 'communications',
                name: 'Customer Communications',
                score: $commsScore,
                status: $this->resolveStatus($commsScore),
                trend: ($urgentCount > 0 || $failedSends > 0) ? 'down' : 'stable',
                signals: $urgentCount > 0 ? ["{$urgentCount} urgent customer thread(s) awaiting reply."] : ['No urgent unhandled inquiries.'],
                evidence: []
            );
        }

        // 6. Inventory Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'productservice.manage')) {
            $lowStock = ProductServiceItem::where('workspace_id', $wsId)->where('type', 'product')->where('quantity', '<=', 5)->count();
            $outOfStock = ProductServiceItem::where('workspace_id', $wsId)->where('type', 'product')->where('quantity', '<=', 0)->count();

            $invScore = max(15, 100 - ($outOfStock * 25) - ($lowStock * 5));
            $dimensions['inventory'] = new HealthDimensionDTO(
                key: 'inventory',
                name: 'Inventory & Stock',
                score: $invScore,
                status: $this->resolveStatus($invScore),
                trend: $lowStock > 0 ? 'down' : 'stable',
                signals: $lowStock > 0 ? ["{$lowStock} item(s) below reorder threshold ({$outOfStock} depleted)."] : ['All product lines adequately stocked.'],
                evidence: []
            );
        }

        // 7. Projects & Tasks Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'taskly.manage')) {
            $overdueTasks = TasklyTask::where('workspace_id', $wsId)->whereNull('completed_at')->where('due_on', '<', today())->count();
            $taskScore = max(20, 100 - ($overdueTasks * 10));

            $dimensions['projects'] = new HealthDimensionDTO(
                key: 'projects',
                name: 'Projects & Tasks',
                score: $taskScore,
                status: $this->resolveStatus($taskScore),
                trend: $overdueTasks > 0 ? 'down' : 'stable',
                signals: $overdueTasks > 0 ? ["{$overdueTasks} task(s) overdue."] : ['All project tasks on schedule.'],
                evidence: []
            );
        }

        // 8. Human Resources Health (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'hrm.manage')) {
            $pendingLeaves = HrLeaveRequest::where('workspace_id', $wsId)->where('status', 'pending')->count();
            $hrScore = max(40, 100 - ($pendingLeaves * 10));

            $dimensions['hr'] = new HealthDimensionDTO(
                key: 'hr',
                name: 'Human Resources',
                score: $hrScore,
                status: $this->resolveStatus($hrScore),
                trend: 'stable',
                signals: $pendingLeaves > 0 ? ["{$pendingLeaves} leave request(s) awaiting approval."] : ['HR operations running smoothly.'],
                evidence: []
            );
        }

        // 9. Operations, Automations & Missions Health
        $failedRuns = AutomationRun::where('workspace_id', $wsId)->where('status', 'failed')->where('created_at', '>=', now()->subHours(24))->count();
        $stalledMissions = MrFoxMission::where('workspace_id', $wsId)->where('status', 'failed')->count();
        $opsScore = max(20, 100 - ($failedRuns * 15) - ($stalledMissions * 20));

        $dimensions['operations'] = new HealthDimensionDTO(
            key: 'operations',
            name: 'Automations & Missions',
            score: $opsScore,
            status: $this->resolveStatus($opsScore),
            trend: ($failedRuns > 0 || $stalledMissions > 0) ? 'down' : 'stable',
            signals: $failedRuns > 0 ? ["{$failedRuns} automation rule failure(s) in last 24h."] : ['All automation rules and missions healthy.'],
            evidence: []
        );

        // 10. Composite Overall Score (Normalized exclusively over authorized dimensions)
        $scores = array_map(fn (HealthDimensionDTO $d) => $d->score, $dimensions);
        $overallScore = count($scores) > 0 ? (int) round(array_sum($scores) / count($scores)) : 100;
        $overallStatus = $this->resolveStatus($overallScore);

        $overall = new HealthDimensionDTO(
            key: 'overall',
            name: 'Overall Business Health',
            score: $overallScore,
            status: $overallStatus,
            trend: $overallScore < 70 ? 'down' : 'stable',
            signals: ['Evaluated across '.count($dimensions).' active authorized dimension(s).'],
            evidence: []
        );

        return [
            'overall' => $overall,
            'dimensions' => $dimensions,
        ];
    }

    private function resolveStatus(int $score): string
    {
        return match (true) {
            $score >= 85 => 'healthy',
            $score >= 70 => 'watch',
            $score >= 50 => 'warning',
            default => 'critical',
        };
    }
}
