<?php

namespace App\Domain\MrFox\Insights;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\CrmLead;
use App\Models\HrLeaveRequest;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;

class BusinessInsightService
{
    public function generateInsights(ToolContext $context): array
    {
        $workspaceId = $context->getWorkspaceId();
        $insights = [];

        // 1. Receivables & Overdue Sales Invoices
        $overdueInvoices = SalesInvoice::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [1, 2])
            ->where('due_date', '<', now())
            ->get();

        if ($overdueInvoices->isNotEmpty()) {
            $totalOverdue = (float) $overdueInvoices->sum('total_amount');
            $evidence = $overdueInvoices->take(5)->map(fn ($inv) => [
                'type' => 'invoice',
                'id' => $inv->id,
                'label' => $inv->invoice_id,
                'amount' => (float) $inv->total_amount,
                'route' => "/sales/invoices/{$inv->id}",
            ])->all();

            $insights[] = [
                'id' => 'insight_receivables_overdue',
                'category' => 'finance',
                'severity' => 'warning',
                'title' => 'Overdue Receivables Require Collection',
                'summary' => sprintf('%d posted invoice(s) totaling $%s are past their due date.', $overdueInvoices->count(), number_format($totalOverdue, 2)),
                'evidence' => $evidence,
                'suggested_action' => 'Send payment reminders or review customer account statements.',
            ];
        }

        // 2. Low Stock Inventory Warnings
        $lowStockProducts = ProductServiceItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'product')
            ->where('reorder_level', '>', 0)
            ->get();

        if ($lowStockProducts->isNotEmpty()) {
            $evidence = $lowStockProducts->take(5)->map(fn ($p) => [
                'type' => 'product',
                'id' => $p->id,
                'label' => "{$p->name} ({$p->sku})",
                'min_stock' => (int) $p->reorder_level,
                'route' => '/products',
            ])->all();

            $insights[] = [
                'id' => 'insight_inventory_low_stock',
                'category' => 'inventory',
                'severity' => 'warning',
                'title' => 'Inventory Replenishment Needed',
                'summary' => sprintf('%d catalog product(s) have configured reorder thresholds.', $lowStockProducts->count()),
                'evidence' => $evidence,
                'suggested_action' => 'Create purchase orders or transfer inventory between warehouses.',
            ];
        }

        // 3. Overdue Taskly Project Tasks
        $overdueTasks = TasklyTask::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('completed_at')
            ->where('due_on', '<', now())
            ->with('project')
            ->get();

        if ($overdueTasks->isNotEmpty()) {
            $evidence = $overdueTasks->take(5)->map(fn ($t) => [
                'type' => 'task',
                'id' => $t->id,
                'label' => $t->title,
                'project' => $t->project?->name ?? 'Project',
                'due_date' => $t->due_on?->format('Y-m-d'),
                'route' => "/tasks/{$t->id}",
            ])->all();

            $insights[] = [
                'id' => 'insight_tasks_overdue',
                'category' => 'projects',
                'severity' => 'info',
                'title' => 'Overdue Sprint Tasks',
                'summary' => sprintf('%d active task(s) have passed their scheduled delivery deadlines.', $overdueTasks->count()),
                'evidence' => $evidence,
                'suggested_action' => 'Review milestone assignees and re-estimate deliverable timelines.',
            ];
        }

        // 4. Pending HR Leave Approvals
        $pendingLeaves = HrLeaveRequest::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->with(['employee', 'type'])
            ->get();

        if ($pendingLeaves->isNotEmpty()) {
            $evidence = $pendingLeaves->take(5)->map(fn ($l) => [
                'type' => 'leave',
                'id' => $l->id,
                'label' => "{$l->employee?->name} ({$l->type?->name})",
                'days' => $l->days,
                'route' => '/hrm/leaves',
            ])->all();

            $insights[] = [
                'id' => 'insight_hr_pending_leaves',
                'category' => 'hr',
                'severity' => 'info',
                'title' => 'Pending Employee Leave Requests',
                'summary' => sprintf('%d leave request(s) are awaiting managerial approval.', $pendingLeaves->count()),
                'evidence' => $evidence,
                'suggested_action' => 'Review department calendar coverage and approve or reject submissions.',
            ];
        }

        // 5. CRM Lead Pipeline Velocity
        $openLeadsCount = CrmLead::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('converted_at')
            ->count();

        if ($openLeadsCount > 0) {
            $insights[] = [
                'id' => 'insight_crm_pipeline_active',
                'category' => 'crm',
                'severity' => 'positive',
                'title' => 'Active Inbound Lead Pipeline',
                'summary' => sprintf('%d active prospect(s) are currently progressing through deal conversion funnels.', $openLeadsCount),
                'evidence' => [
                    ['type' => 'crm', 'id' => 0, 'label' => 'CRM Deals & Leads', 'route' => '/crm/leads'],
                ],
                'suggested_action' => 'Review prospect engagement activity and schedule discovery calls.',
            ];
        }

        return $insights;
    }
}
