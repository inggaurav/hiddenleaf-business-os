<?php

namespace App\Domain\CommandCenter\Signals;

use App\Domain\CommandCenter\DTO\BusinessSignalDTO;
use App\Models\AutomationRun;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\CrmLead;
use App\Models\HelpdeskTicket;
use App\Models\MrFoxMission;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

class SignalDetector
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Detect all active signals across accessible business domains.
     *
     * @return BusinessSignalDTO[]
     */
    public function detectSignals(User $user, Workspace $workspace): array
    {
        $signals = [];
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

        // 1. Finance Signals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'account.manage')) {
            // Overdue Receivables
            $overdueInvoices = SalesInvoice::where('workspace_id', $wsId)
                ->whereNotIn('status', [0, 3])
                ->where('due_date', '<', today())
                ->get();

            $overdueReceivablesSum = (float) $overdueInvoices->sum('total_amount');
            $overdueCount = $overdueInvoices->count();

            if ($overdueCount > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'finance.overdue_receivables',
                    category: 'finance',
                    type: 'overdue_receivables',
                    severity: $overdueReceivablesSum > 10000 || $overdueCount >= 5 ? 'critical' : 'warning',
                    title: "{$overdueCount} Overdue Sales Invoice(s)",
                    description: sprintf('Totaling $%s outstanding past due date.', number_format($overdueReceivablesSum, 2)),
                    value: $overdueReceivablesSum,
                    threshold: 0,
                    trend: 'up',
                    evidence: $overdueInvoices->take(5)->map(fn ($inv) => [
                        'type' => 'sales_invoice',
                        'id' => $inv->id,
                        'label' => "Invoice #{$inv->invoice_id} ({$inv->customer?->name}): $".number_format((float) $inv->total_amount, 2),
                        'route' => "/sales/invoices/{$inv->id}",
                    ])->all()
                );
            }

            // Overdue Payables
            $overdueBills = PurchaseInvoice::where('workspace_id', $wsId)
                ->whereNotIn('status', [0, 3])
                ->where('due_date', '<', today())
                ->get();

            $overdueBillsSum = (float) $overdueBills->sum('total_amount');
            if ($overdueBills->count() > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'finance.overdue_payables',
                    category: 'finance',
                    type: 'overdue_payables',
                    severity: $overdueBillsSum > 10000 ? 'warning' : 'attention',
                    title: "{$overdueBills->count()} Overdue Vendor Bill(s)",
                    description: sprintf('Totaling $%s payable past due date.', number_format($overdueBillsSum, 2)),
                    value: $overdueBillsSum,
                    threshold: 0,
                    trend: 'stable',
                    evidence: $overdueBills->take(5)->map(fn ($bill) => [
                        'type' => 'purchase_invoice',
                        'id' => $bill->id,
                        'label' => "Bill #{$bill->invoice_id} ({$bill->vendor?->name}): $".number_format((float) $bill->total_amount, 2),
                        'route' => "/purchases/invoices/{$bill->id}",
                    ])->all()
                );
            }
        }

        // 2. CRM & Sales Signals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'crm.manage')) {
            // Dormant Qualified Leads (Inactive > 5 days)
            $dormantLeads = CrmLead::where('workspace_id', $wsId)
                ->where('status', 'qualified')
                ->where('updated_at', '<', now()->subDays(5))
                ->get();

            if ($dormantLeads->count() > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'crm.inactive_warm_leads',
                    category: 'crm',
                    type: 'inactive_warm_leads',
                    severity: 'attention',
                    title: "{$dormantLeads->count()} Qualified Lead(s) Inactive > 5 Days",
                    description: 'Warm prospective clients with no recent sales activity.',
                    value: $dormantLeads->count(),
                    threshold: 0,
                    trend: 'stable',
                    evidence: $dormantLeads->take(5)->map(fn ($l) => [
                        'type' => 'crm_lead',
                        'id' => $l->id,
                        'label' => "Lead: {$l->name} ({$l->company})",
                        'route' => "/crm/leads/{$l->id}",
                    ])->all()
                );
            }
        }

        // 3. Communications Signals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'communications.view')) {
            // Urgent Inbound Conversations
            $urgentConvs = CommunicationConversation::where('workspace_id', $wsId)
                ->where('priority_score', '>=', 75)
                ->whereIn('status', ['open', 'in_progress'])
                ->get();

            if ($urgentConvs->count() > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'communications.urgent',
                    category: 'communications',
                    type: 'urgent_conversations',
                    severity: 'critical',
                    title: "{$urgentConvs->count()} Urgent Customer Conversation(s)",
                    description: 'Inbound customer threads requiring immediate operator response.',
                    value: $urgentConvs->count(),
                    threshold: 0,
                    trend: 'up',
                    evidence: $urgentConvs->take(5)->map(fn ($c) => [
                        'type' => 'communication_conversation',
                        'id' => $c->id,
                        'label' => "{$c->provider} from {$c->participant_name}: \"{$c->subject}\"",
                        'route' => '/communications/inbox',
                    ])->all()
                );
            }

            // Failed Outbound Messages
            $failedMessages = CommunicationMessage::where('workspace_id', $wsId)
                ->where('delivery_status', 'failed')
                ->where('created_at', '>=', now()->subHours(48))
                ->get();

            if ($failedMessages->count() > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'communications.failed_delivery',
                    category: 'communications',
                    type: 'failed_delivery',
                    severity: 'warning',
                    title: "{$failedMessages->count()} Outbound Communication Delivery Failure(s)",
                    description: 'Outbound emails, WhatsApp, or Slack messages failed in the last 48 hours.',
                    value: $failedMessages->count(),
                    threshold: 0,
                    trend: 'up',
                    evidence: $failedMessages->take(5)->map(fn ($m) => [
                        'type' => 'communication_message',
                        'id' => $m->id,
                        'label' => "Failed to deliver message #{$m->id} to {$m->conversation?->participant_name}",
                        'route' => '/communications/inbox',
                    ])->all()
                );
            }
        }

        // 4. Inventory Signals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'productservice.manage')) {
            // Low Stock Items
            $lowStockItems = ProductServiceItem::where('workspace_id', $wsId)
                ->where('type', 'product')
                ->whereHas('stocks', fn ($q) => $q->where('quantity', '<=', 5))
                ->with('stocks')
                ->get();

            if ($lowStockItems->count() > 0) {
                $outOfStock = $lowStockItems->filter(fn ($p) => $p->stocks->sum('quantity') <= 0)->count();
                $signals[] = new BusinessSignalDTO(
                    id: 'inventory.low_stock',
                    category: 'inventory',
                    type: 'low_stock',
                    severity: $outOfStock > 0 ? 'critical' : 'warning',
                    title: "{$lowStockItems->count()} Low / Depleted Inventory Item(s)",
                    description: sprintf('%d item(s) are completely out of stock; restock required.', $outOfStock),
                    value: $lowStockItems->count(),
                    threshold: 5,
                    trend: 'down',
                    evidence: $lowStockItems->take(5)->map(fn ($p) => [
                        'type' => 'product_item',
                        'id' => $p->id,
                        'label' => "Product: {$p->name} (Qty: {$p->quantity})",
                        'route' => "/productservice/{$p->id}",
                    ])->all()
                );
            }
        }

        // 5. Tasks & Projects Signals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'taskly.manage')) {
            $overdueTasks = TasklyTask::where('workspace_id', $wsId)
                ->whereNull('completed_at')
                ->where('due_on', '<', today())
                ->get();

            if ($overdueTasks->count() > 0) {
                $signals[] = new BusinessSignalDTO(
                    id: 'tasks.overdue',
                    category: 'tasks',
                    type: 'overdue_tasks',
                    severity: $overdueTasks->count() >= 5 ? 'warning' : 'attention',
                    title: "{$overdueTasks->count()} Overdue Project Task(s)",
                    description: 'Scheduled tasks past their committed completion milestone.',
                    value: $overdueTasks->count(),
                    threshold: 0,
                    trend: 'up',
                    evidence: $overdueTasks->take(5)->map(fn ($t) => [
                        'type' => 'taskly_task',
                        'id' => $t->id,
                        'label' => "Task: {$t->title} (Project: {$t->project?->name})",
                        'route' => "/taskly/projects/{$t->project_id}",
                    ])->all()
                );
            }
        }

        // 6. Helpdesk Signals
        $escalatedTickets = HelpdeskTicket::where('workspace_id', $wsId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where('priority', 'high')
            ->get();

        if ($escalatedTickets->count() > 0) {
            $signals[] = new BusinessSignalDTO(
                id: 'helpdesk.escalated',
                category: 'helpdesk',
                type: 'escalated_tickets',
                severity: 'warning',
                title: "{$escalatedTickets->count()} High-Priority Support Ticket(s)",
                description: 'Client escalations requiring technical or support investigation.',
                value: $escalatedTickets->count(),
                threshold: 0,
                trend: 'up',
                evidence: $escalatedTickets->take(5)->map(fn ($tk) => [
                    'type' => 'helpdesk_ticket',
                    'id' => $tk->id,
                    'label' => "Ticket #{$tk->ticket_id}: {$tk->name}",
                    'route' => "/helpdesk/tickets/{$tk->id}",
                ])->all()
            );
        }

        // 7. Automations & Missions Signals
        $failedRuns = AutomationRun::where('workspace_id', $wsId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        if ($failedRuns->count() > 0) {
            $signals[] = new BusinessSignalDTO(
                id: 'automation.failed_runs',
                category: 'operations',
                type: 'failed_automations',
                severity: 'warning',
                title: "{$failedRuns->count()} Automation Rule Failure(s) in Last 24h",
                description: 'Deterministic automations that threw exceptions or validation failures.',
                value: $failedRuns->count(),
                threshold: 0,
                trend: 'up',
                evidence: $failedRuns->take(5)->map(fn ($r) => [
                    'type' => 'automation_run',
                    'id' => $r->id,
                    'label' => "Run #{$r->id} ({$r->trigger_event}): {$r->error_message}",
                    'route' => '/automations',
                ])->all()
            );
        }

        $pendingMissions = MrFoxMission::where('workspace_id', $wsId)
            ->where('status', 'waiting_for_approval')
            ->get();

        if ($pendingMissions->count() > 0) {
            $signals[] = new BusinessSignalDTO(
                id: 'mission.waiting_approval',
                category: 'operations',
                type: 'mission_waiting_approval',
                severity: 'attention',
                title: "{$pendingMissions->count()} Mission(s) Paused for Human Approval",
                description: 'Mr. Fox executive missions paused pending review of high-risk actions.',
                value: $pendingMissions->count(),
                threshold: 0,
                trend: 'stable',
                evidence: $pendingMissions->take(5)->map(fn ($m) => [
                    'type' => 'mr_fox_mission',
                    'id' => $m->id,
                    'label' => "Mission: {$m->name}",
                    'route' => '/missions',
                ])->all()
            );
        }

        return $signals;
    }
}
