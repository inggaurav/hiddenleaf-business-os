<?php

namespace App\Domain\Automation\Triggers;

use App\Domain\Automation\Contracts\AutomationTriggerContract;
use InvalidArgumentException;

class TriggerRegistry
{
    /** @var array<string, AutomationTriggerContract> */
    private array $triggers = [];

    public function __construct()
    {
        // CRM
        $this->register(new GenericTrigger('crm.lead.created', 'Fired when a new CRM lead is captured', 'crm'));
        $this->register(new GenericTrigger('crm.lead.updated', 'Fired when CRM lead details are updated', 'crm'));
        $this->register(new GenericTrigger('crm.lead.stage_changed', 'Fired when a lead moves pipeline stage', 'crm'));
        $this->register(new GenericTrigger('crm.deal.created', 'Fired when a new opportunity deal is created', 'crm'));
        $this->register(new GenericTrigger('crm.deal.stage_changed', 'Fired when a deal moves to a new stage', 'crm'));

        // Sales & Procurement
        $this->register(new GenericTrigger('sales.invoice.created', 'Fired when a sales invoice is drafted or issued', 'sales'));
        $this->register(new GenericTrigger('sales.invoice.overdue', 'Fired when a sales invoice passes its due date', 'sales'));
        $this->register(new GenericTrigger('sales.invoice.payment_received', 'Fired when customer payment is recorded', 'sales'));
        $this->register(new GenericTrigger('sales.proposal.accepted', 'Fired when client accepts a sales proposal', 'sales'));
        $this->register(new GenericTrigger('procurement.bill.created', 'Fired when a vendor purchase bill is recorded', 'procurement'));
        $this->register(new GenericTrigger('procurement.bill.due', 'Fired when a vendor bill reaches due date', 'procurement'));
        $this->register(new GenericTrigger('procurement.vendor.payment_recorded', 'Fired when payment is made to vendor', 'procurement'));

        // Inventory
        $this->register(new GenericTrigger('inventory.stock.low', 'Fired when item inventory falls below threshold', 'productservice'));
        $this->register(new GenericTrigger('inventory.stock.adjusted', 'Fired when inventory adjustment is saved', 'productservice'));
        $this->register(new GenericTrigger('inventory.stock.transferred', 'Fired when items move between warehouses', 'productservice'));

        // Tasks & Projects
        $this->register(new GenericTrigger('tasks.task.created', 'Fired when a new task is assigned or added', 'taskly'));
        $this->register(new GenericTrigger('tasks.task.assigned', 'Fired when a task is delegated to a user', 'taskly'));
        $this->register(new GenericTrigger('tasks.task.overdue', 'Fired when a task passes its deadline', 'taskly'));
        $this->register(new GenericTrigger('tasks.task.completed', 'Fired when a task status moves to done', 'taskly'));

        // HRM
        $this->register(new GenericTrigger('hrm.leave.requested', 'Fired when employee submits a leave request', 'hrm'));
        $this->register(new GenericTrigger('hrm.leave.approved', 'Fired when manager approves leave request', 'hrm'));
        $this->register(new GenericTrigger('hrm.employee.created', 'Fired when a new employee profile is added', 'hrm'));

        // Communications
        $this->register(new GenericTrigger('communications.message.received', 'Fired when an inbound message arrives', 'communications'));
        $this->register(new GenericTrigger('communications.message.unread_timeout', 'Fired when message remains unread past SLA', 'communications'));
        $this->register(new GenericTrigger('communications.message.urgent', 'Fired when message has priority score >= 75', 'communications'));
        $this->register(new GenericTrigger('communications.message.failed', 'Fired when outbound delivery fails', 'communications'));

        // Helpdesk
        $this->register(new GenericTrigger('helpdesk.ticket.created', 'Fired when a new support ticket is opened', 'helpdesk'));
        $this->register(new GenericTrigger('helpdesk.ticket.escalated', 'Fired when ticket priority is escalated', 'helpdesk'));
        $this->register(new GenericTrigger('helpdesk.ticket.closed', 'Fired when ticket is resolved or closed', 'helpdesk'));
    }

    public function register(AutomationTriggerContract $trigger): void
    {
        $this->triggers[$trigger->name()] = $trigger;
    }

    public function get(string $name): AutomationTriggerContract
    {
        if (! isset($this->triggers[$name])) {
            throw new InvalidArgumentException("Trigger '{$name}' is not registered.");
        }

        return $this->triggers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->triggers[$name]);
    }

    public function all(): array
    {
        return $this->triggers;
    }
}
