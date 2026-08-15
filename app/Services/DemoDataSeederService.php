<?php

namespace App\Services;

use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HelpdeskTicket;
use App\Models\HrEmployee;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DemoDataSeederService
{
    public function seedDemoData(User $user, Workspace $workspace): array
    {
        return DB::transaction(function () use ($user, $workspace) {
            $orgId = $workspace->organization_id;
            $wsId = $workspace->id;

            $pipeline = CrmPipeline::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Demo Sales Pipeline',
            ], ['is_default' => true]);

            $stageLead = CrmStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'name' => 'Qualified'],
                ['position' => 1]
            );
            $stageNegotiation = CrmStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'name' => 'Negotiation'],
                ['position' => 2]
            );

            $lead = CrmLead::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'email' => 'partnerships@apexcloud.io',
            ], [
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stageLead->id,
                'name' => '[DEMO] Apex Cloud Systems',
                'phone' => '+1 (555) 234-5678',
                'company' => 'Apex Cloud Inc.',
                'estimated_value' => 18500.00,
                'status' => 'qualified',
                'created_by' => $user->id,
            ]);

            CrmDeal::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => '[DEMO] Quantum Robotics Expansion',
            ], [
                'lead_id' => $lead->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stageNegotiation->id,
                'value' => 48000.00,
                'status' => 'open',
            ]);

            $category = ProductServiceCategory::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Software & Hardware',
            ], [
                'type' => 'product',
                'color' => '#4f46e5',
            ]);

            $item1 = ProductServiceItem::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'sku' => 'DEMO-GW-100',
            ], [
                'category_id' => $category->id,
                'name' => '[DEMO] Enterprise Edge Gateway',
                'type' => 'product',
                'reorder_level' => 10,
                'sale_price' => 2500.00,
                'purchase_price' => 1400.00,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            ProductServiceItem::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'sku' => 'DEMO-SAAS-01',
            ], [
                'category_id' => $category->id,
                'name' => '[DEMO] Business OS Annual Subscription',
                'type' => 'service',
                'reorder_level' => 0,
                'sale_price' => 12000.00,
                'purchase_price' => 0.00,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            $warehouse = Warehouse::firstOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Main Warehouse',
            ], [
                'address' => 'HQ Facility',
                'city' => 'Primary',
                'zip_code' => '00000',
            ]);

            WarehouseStock::updateOrCreate([
                'product_id' => $item1->id,
                'warehouse_id' => $warehouse->id,
            ], [
                'quantity' => 4,
            ]);

            $invoice = SalesInvoice::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'invoice_id' => 'INV-DEMO-901',
            ], [
                'issue_date' => now()->subDays(20)->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'total_amount' => 18500.00,
                'status' => 'sent',
            ]);

            SalesInvoiceItem::updateOrCreate([
                'invoice_id' => $invoice->id,
                'item_id' => $item1->id,
            ], [
                'quantity' => 2,
                'price' => 2500.00,
                'total' => 5000.00,
            ]);

            PurchaseInvoice::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'invoice_id' => 'PINV-DEMO-301',
            ], [
                'warehouse_id' => $warehouse->id,
                'purchase_date' => now()->subDays(12)->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'total_amount' => 4200.00,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            HrEmployee::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'employee_number' => 'DEMO-EMP-001',
            ], [
                'name' => '[DEMO] Priya Sharma',
                'email' => 'priya.demo@hiddenleaf.local',
                'joined_at' => now()->subMonths(8)->toDateString(),
                'basic_salary' => 65000.00,
                'status' => 'active',
            ]);

            HelpdeskTicket::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'ticket_id' => 'TKT-DEMO-101',
            ], [
                'name' => 'Apex Cloud Systems',
                'email' => 'support@apexcloud.io',
                'subject' => '[DEMO] API sync delay during onboarding',
                'status' => 'open',
                'priority' => 'high',
                'description' => 'Customer reports delayed synchronization during the onboarding cutover. Review logs and provide an ETA.',
                'created_by' => $user->id,
            ]);

            $commAccount = CommunicationAccount::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'provider' => 'internal',
                'external_account_id' => 'demo_support_bot',
            ], [
                'display_name' => 'Demo Support Inbox',
                'status' => 'connected',
            ]);

            $conversation = CommunicationConversation::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'provider' => 'internal',
                'external_thread_id' => 'demo_internal_urgent_contract',
            ], [
                'account_id' => $commAccount->id,
                'subject' => '[DEMO] URGENT: Contract Clarification for Q3 Delivery',
                'participant_name' => 'Sarah Connor (Cyberdyne Systems)',
                'participant_identifier' => 'sconnor@cyberdyne.com',
                'last_message_preview' => 'Can we finalize the signed SLA agreement before end of day tomorrow?',
                'last_message_at' => now()->subMinutes(15),
                'priority_score' => 88,
                'status' => 'open',
                'unread_count' => 1,
            ]);

            CommunicationMessage::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'conversation_id' => $conversation->id,
                'provider_message_id' => 'msg_demo_urgent_contract',
            ], [
                'direction' => 'inbound',
                'sender_name' => 'Sarah Connor',
                'sender_identifier' => 'sconnor@cyberdyne.com',
                'body_text' => 'Can we finalize the signed SLA agreement before end of day tomorrow?',
                'delivery_status' => 'delivered',
                'sent_at' => now()->subMinutes(15),
            ]);

            $project = TasklyProject::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => '[DEMO] Client ERP Onboarding',
            ], [
                'status' => 'in_progress',
                'created_by' => $user->id,
            ]);

            $taskStage = TasklyStage::updateOrCreate([
                'project_id' => $project->id,
                'name' => 'To Do',
            ], ['position' => 0]);

            TasklyTask::updateOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'project_id' => $project->id,
                'title' => '[DEMO] Finalize API Data Mapping & Cutover',
            ], [
                'stage_id' => $taskStage->id,
                'priority' => 'high',
                'due_date' => now()->subDays(2)->toDateString(),
            ]);

            return [
                'leads_count' => CrmLead::where('workspace_id', $wsId)->where('name', 'like', '[DEMO]%')->count(),
                'deals_count' => CrmDeal::where('workspace_id', $wsId)->where('name', 'like', '[DEMO]%')->count(),
                'products_count' => ProductServiceItem::where('workspace_id', $wsId)->where('sku', 'like', 'DEMO-%')->count(),
                'invoices_count' => SalesInvoice::where('workspace_id', $wsId)->where('invoice_id', 'like', 'INV-DEMO-%')->count(),
                'purchase_invoices_count' => PurchaseInvoice::where('workspace_id', $wsId)->where('invoice_id', 'like', 'PINV-DEMO-%')->count(),
                'employees_count' => HrEmployee::where('workspace_id', $wsId)->where('employee_number', 'like', 'DEMO-%')->count(),
                'tickets_count' => HelpdeskTicket::where('workspace_id', $wsId)->where('ticket_id', 'like', 'TKT-DEMO-%')->count(),
                'conversations_count' => CommunicationConversation::where('workspace_id', $wsId)->where('subject', 'like', '[DEMO]%')->count(),
                'tasks_count' => TasklyTask::where('workspace_id', $wsId)->where('title', 'like', '[DEMO]%')->count(),
            ];
        });
    }

    public function resetDemoData(Workspace $workspace): int
    {
        $wsId = $workspace->id;

        return DB::transaction(function () use ($wsId) {
            $deleted = 0;

            $deleted += CommunicationMessage::where('workspace_id', $wsId)
                ->where('provider_message_id', 'like', 'msg_demo_%')
                ->delete();
            $deleted += CommunicationConversation::where('workspace_id', $wsId)
                ->where('subject', 'like', '[DEMO]%')
                ->delete();

            $deleted += TasklyTask::where('workspace_id', $wsId)
                ->where('title', 'like', '[DEMO]%')
                ->delete();
            $deleted += TasklyProject::where('workspace_id', $wsId)
                ->where('name', 'like', '[DEMO]%')
                ->delete();

            $invoiceIds = SalesInvoice::where('workspace_id', $wsId)
                ->where('invoice_id', 'like', 'INV-DEMO-%')
                ->pluck('id');
            if ($invoiceIds->isNotEmpty()) {
                $deleted += SalesInvoiceItem::whereIn('invoice_id', $invoiceIds)->delete();
                $deleted += SalesInvoice::whereIn('id', $invoiceIds)->delete();
            }

            $deleted += PurchaseInvoice::where('workspace_id', $wsId)
                ->where('invoice_id', 'like', 'PINV-DEMO-%')
                ->delete();

            $deleted += HelpdeskTicket::where('workspace_id', $wsId)
                ->where('ticket_id', 'like', 'TKT-DEMO-%')
                ->delete();

            $deleted += HrEmployee::where('workspace_id', $wsId)
                ->where('employee_number', 'like', 'DEMO-%')
                ->delete();

            $deleted += CrmDeal::where('workspace_id', $wsId)
                ->where('name', 'like', '[DEMO]%')
                ->delete();
            $deleted += CrmLead::where('workspace_id', $wsId)
                ->where('name', 'like', '[DEMO]%')
                ->delete();

            $productIds = ProductServiceItem::where('workspace_id', $wsId)
                ->where('sku', 'like', 'DEMO-%')
                ->pluck('id');
            if ($productIds->isNotEmpty()) {
                $deleted += WarehouseStock::whereIn('product_id', $productIds)->delete();
                $deleted += ProductServiceItem::whereIn('id', $productIds)->delete();
            }

            return $deleted;
        });
    }
}
