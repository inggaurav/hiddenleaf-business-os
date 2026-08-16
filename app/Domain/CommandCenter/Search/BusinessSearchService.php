<?php

namespace App\Domain\CommandCenter\Search;

use App\Domain\CommandCenter\DTO\SearchResultDTO;
use App\Models\AutomationRule;
use App\Models\CommunicationConversation;
use App\Models\CrmLead;
use App\Models\HrEmployee;
use App\Models\MrFoxKnowledgeDocument;
use App\Models\MrFoxMission;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

class BusinessSearchService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Search across all accessible business entities in the current workspace.
     *
     * @return SearchResultDTO[]
     */
    public function search(User $user, Workspace $workspace, string $query, int $limit = 20): array
    {
        $cleanQuery = trim($query);
        if (strlen($cleanQuery) < 2) {
            return [];
        }

        $results = [];
        $wsId = $workspace->id;
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');

        // 1. Invoices & Billing (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'account.manage')) {
            $invoices = SalesInvoice::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('invoice_id', 'like', "%{$cleanQuery}%")
                        ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$cleanQuery}%"));
                })
                ->take(5)
                ->get();

            foreach ($invoices as $inv) {
                $results[] = new SearchResultDTO(
                    type: 'invoice',
                    id: $inv->id,
                    title: "Sales Invoice #{$inv->invoice_id}",
                    subtitle: sprintf('Customer: %s • Amount: $%s • Status: %s', $inv->customer?->name ?? 'Direct', number_format((float) $inv->total_amount, 2), strtoupper($inv->status)),
                    route: "/sales/invoices/{$inv->id}",
                    badge: 'FINANCE'
                );
            }
        }

        // 2. CRM Leads & Deals (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'crm.manage')) {
            $leads = CrmLead::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('company', 'like', "%{$cleanQuery}%")
                        ->orWhere('email', 'like', "%{$cleanQuery}%");
                })
                ->take(5)
                ->get();

            foreach ($leads as $l) {
                $results[] = new SearchResultDTO(
                    type: 'lead',
                    id: $l->id,
                    title: "Lead: {$l->name}",
                    subtitle: sprintf('Company: %s • Email: %s • Status: %s', $l->company ?? 'N/A', $l->email ?? 'N/A', ucfirst($l->status)),
                    route: "/crm/leads/{$l->id}",
                    badge: 'CRM'
                );
            }
        }

        // 3. Communications Inbox (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'communications.view')) {
            $convs = CommunicationConversation::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('subject', 'like', "%{$cleanQuery}%")
                        ->orWhere('participant_name', 'like', "%{$cleanQuery}%")
                        ->orWhere('participant_identifier', 'like', "%{$cleanQuery}%");
                })
                ->take(5)
                ->get();

            foreach ($convs as $c) {
                $results[] = new SearchResultDTO(
                    type: 'communication',
                    id: $c->id,
                    title: "Message from {$c->participant_name}",
                    subtitle: sprintf('Subject: %s • Channel: %s • Priority: %d', $c->subject ?? 'No Subject', strtoupper($c->provider), $c->priority_score),
                    route: '/communications/inbox',
                    badge: 'COMMS'
                );
            }
        }

        // 4. Products & Services (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'productservice.manage')) {
            $products = ProductServiceItem::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('sku', 'like', "%{$cleanQuery}%");
                })
                ->take(5)
                ->get();

            foreach ($products as $p) {
                $results[] = new SearchResultDTO(
                    type: 'product',
                    id: $p->id,
                    title: "Product: {$p->name}",
                    subtitle: sprintf('SKU: %s • Price: $%s • Qty: %d', $p->sku ?? 'N/A', number_format((float) $p->sale_price, 2), $p->quantity),
                    route: "/productservice/{$p->id}",
                    badge: 'INVENTORY'
                );
            }
        }

        // 5. Tasks & Projects (Permission Gated)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'taskly.manage')) {
            $tasks = TasklyTask::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('title', 'like', "%{$cleanQuery}%")
                        ->orWhere('description', 'like', "%{$cleanQuery}%");
                })
                ->take(5)
                ->get();

            foreach ($tasks as $t) {
                $results[] = new SearchResultDTO(
                    type: 'task',
                    id: $t->id,
                    title: "Task: {$t->title}",
                    subtitle: sprintf('Project: %s • Priority: %s', $t->project?->name ?? 'General', ucfirst($t->priority)),
                    route: "/taskly/projects/{$t->project_id}",
                    badge: 'PROJECTS'
                );
            }
        }

        // 6. Employees (STRICTLY Gated by HR permission - Phase 29/49)
        if ($isSuperAdmin || $this->permissionService->allows($user, $workspace, 'hrm.manage')) {
            $employees = HrEmployee::where('workspace_id', $wsId)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', "%{$cleanQuery}%")
                        ->orWhere('email', 'like', "%{$cleanQuery}%");
                })
                ->take(5)
                ->get();

            foreach ($employees as $emp) {
                $results[] = new SearchResultDTO(
                    type: 'employee',
                    id: $emp->id,
                    title: "Employee: {$emp->name}",
                    subtitle: sprintf('Designation: %s • Email: %s', $emp->designation ?? 'Staff', $emp->email),
                    route: "/hrm/employees/{$emp->id}",
                    badge: 'HRM'
                );
            }
        }

        // 7. Knowledge Documents
        $docs = MrFoxKnowledgeDocument::where('workspace_id', $wsId)
            ->where(function ($q) use ($cleanQuery) {
                $q->where('title', 'like', "%{$cleanQuery}%")
                    ->orWhere('filename', 'like', "%{$cleanQuery}%");
            })
            ->take(5)
            ->get();

        foreach ($docs as $d) {
            $results[] = new SearchResultDTO(
                type: 'knowledge',
                id: $d->id,
                title: "Knowledge: {$d->title}",
                subtitle: sprintf('Type: %s • Visibility: %s', strtoupper($d->file_type), ucfirst($d->visibility)),
                route: '/knowledge',
                badge: 'KNOWLEDGE'
            );
        }

        // 8. Automations & Missions
        $rules = AutomationRule::where('workspace_id', $wsId)
            ->where('name', 'like', "%{$cleanQuery}%")
            ->take(3)
            ->get();

        foreach ($rules as $r) {
            $results[] = new SearchResultDTO(
                type: 'automation',
                id: $r->id,
                title: "Automation: {$r->name}",
                subtitle: sprintf('Trigger: %s • Status: %s', $r->trigger_type, $r->enabled ? 'Active' : 'Paused'),
                route: '/automations',
                badge: 'AUTOMATION'
            );
        }

        $missions = MrFoxMission::where('workspace_id', $wsId)
            ->where(function ($q) use ($cleanQuery) {
                $q->where('name', 'like', "%{$cleanQuery}%")
                    ->orWhere('objective', 'like', "%{$cleanQuery}%");
            })
            ->take(3)
            ->get();

        foreach ($missions as $m) {
            $results[] = new SearchResultDTO(
                type: 'mission',
                id: $m->id,
                title: "Mission: {$m->name}",
                subtitle: sprintf('Objective: %s • Status: %s', substr($m->objective, 0, 60), strtoupper($m->status)),
                route: '/missions',
                badge: 'MISSION'
            );
        }

        return array_slice($results, 0, $limit);
    }
}
