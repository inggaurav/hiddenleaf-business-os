<?php

namespace App\Domain\CommandCenter\Activity;

use App\Domain\CommandCenter\DTO\TimelineItemDTO;
use App\Models\AutomationRun;
use App\Models\CommunicationMessage;
use App\Models\CrmLead;
use App\Models\MrFoxActionProposal;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

class ExecutiveActivityTimelineService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Build unified cross-system executive activity timeline.
     *
     * @return TimelineItemDTO[]
     */
    public function getTimeline(User $user, Workspace $workspace, int $limit = 25): array
    {
        $events = [];
        $wsId = $workspace->id;
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');

        // 1. Sales Invoices
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'account.manage')) {
            $invoices = SalesInvoice::where('workspace_id', $wsId)->latest('updated_at')->take(5)->get();
            foreach ($invoices as $inv) {
                $isPaid = (int) $inv->status === 3;
                $events[] = new TimelineItemDTO(
                    id: "inv_{$inv->id}",
                    domain: 'finance',
                    eventType: $isPaid ? 'invoice.paid' : 'invoice.updated',
                    title: $isPaid ? "Invoice #{$inv->invoice_id} Paid" : "Invoice #{$inv->invoice_id} Created/Updated",
                    description: sprintf('Total: $%s • Status: %s', number_format((float) $inv->total_amount, 2), strtoupper($inv->status)),
                    timestamp: $inv->updated_at->toIso8601String(),
                    actorName: $inv->customer?->name,
                    route: "/sales/invoices/{$inv->id}",
                    severity: $isPaid ? 'success' : 'info',
                    evidence: [['type' => 'invoice', 'id' => $inv->id, 'label' => "Invoice #{$inv->invoice_id}"]]
                );
            }
        }

        // 2. CRM Leads
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'crm.manage')) {
            $leads = CrmLead::where('workspace_id', $wsId)->latest('created_at')->take(5)->get();
            foreach ($leads as $l) {
                $events[] = new TimelineItemDTO(
                    id: "lead_{$l->id}",
                    domain: 'crm',
                    eventType: 'lead.created',
                    title: "New Lead: {$l->name}",
                    description: sprintf('Company: %s • Status: %s', $l->company ?? 'N/A', ucfirst($l->status)),
                    timestamp: $l->created_at->toIso8601String(),
                    actorName: $l->name,
                    route: "/crm/leads/{$l->id}",
                    severity: 'info',
                    evidence: [['type' => 'crm_lead', 'id' => $l->id, 'label' => "Lead: {$l->name}"]]
                );
            }
        }

        // 3. Urgent Communications
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'communications.view')) {
            $messages = CommunicationMessage::where('workspace_id', $wsId)->latest('created_at')->take(5)->get();
            foreach ($messages as $msg) {
                $events[] = new TimelineItemDTO(
                    id: "msg_{$msg->id}",
                    domain: 'comms',
                    eventType: 'communication.message',
                    title: "Message from {$msg->sender_name}",
                    description: substr($msg->body ?? '', 0, 80).'...',
                    timestamp: $msg->created_at->toIso8601String(),
                    actorName: $msg->sender_name,
                    route: '/communications/inbox',
                    severity: $msg->direction === 'inbound' ? 'info' : 'success',
                    evidence: [['type' => 'communication_message', 'id' => $msg->id, 'label' => "Message #{$msg->id}"]]
                );
            }
        }

        // 4. Completed Tasks
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'taskly.manage')) {
            $tasks = TasklyTask::where('workspace_id', $wsId)->whereNotNull('completed_at')->latest('completed_at')->take(5)->get();
            foreach ($tasks as $t) {
                $events[] = new TimelineItemDTO(
                    id: "task_{$t->id}",
                    domain: 'taskly',
                    eventType: 'task.completed',
                    title: "Task Completed: {$t->title}",
                    description: sprintf('Project: %s', $t->project?->name ?? 'General'),
                    timestamp: $t->completed_at->toIso8601String(),
                    route: "/taskly/projects/{$t->project_id}",
                    severity: 'success',
                    evidence: [['type' => 'taskly_task', 'id' => $t->id, 'label' => "Task: {$t->title}"]]
                );
            }
        }

        // 5. Automations
        $runs = AutomationRun::where('workspace_id', $wsId)->latest('created_at')->take(5)->get();
        foreach ($runs as $r) {
            $events[] = new TimelineItemDTO(
                id: "run_{$r->id}",
                domain: 'operations',
                eventType: 'automation.run',
                title: "Automation: {$r->trigger_event}",
                description: sprintf('Status: %s', strtoupper($r->status)),
                timestamp: $r->created_at->toIso8601String(),
                route: '/automations',
                severity: $r->status === 'failed' ? 'warning' : 'info',
                evidence: [['type' => 'automation_run', 'id' => $r->id, 'label' => "Run #{$r->id}"]]
            );
        }

        // 6. Action Proposals / Approvals
        $proposals = MrFoxActionProposal::where('workspace_id', $wsId)->latest('created_at')->take(5)->get();
        foreach ($proposals as $prop) {
            $events[] = new TimelineItemDTO(
                id: "prop_{$prop->id}",
                domain: 'approvals',
                eventType: 'approval.proposal',
                title: "Approval: {$prop->tool_name}",
                description: sprintf('Summary: %s • Status: %s', $prop->human_summary, strtoupper($prop->status)),
                timestamp: $prop->created_at->toIso8601String(),
                actorName: $prop->user?->name,
                route: '/command-center/approvals',
                severity: $prop->status === 'approved' ? 'success' : ($prop->status === 'pending' ? 'warning' : 'info'),
                evidence: [['type' => 'approval_proposal', 'id' => $prop->id, 'label' => "Proposal #{$prop->id}"]]
            );
        }

        // Sort descending by timestamp
        usort($events, fn ($a, $b) => strcmp($b->timestamp, $a->timestamp));

        return array_slice($events, 0, $limit);
    }
}
