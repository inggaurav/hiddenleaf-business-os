<?php

namespace App\Services;

use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DemoDataSeederService
{
    /**
     * Seeds rich, realistic demonstration data into the workspace.
     */
    public function seedDemoData(User $user, Workspace $workspace): array
    {
        return DB::transaction(function () use ($user, $workspace) {
            $orgId = $workspace->organization_id;
            $wsId = $workspace->id;

            // 1. Pipeline & Stages
            $pipeline = CrmPipeline::firstOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Demo Sales Pipeline',
            ], ['is_default' => true]);

            $stageLead = CrmStage::firstOrCreate(['pipeline_id' => $pipeline->id, 'name' => 'Qualified'], ['position' => 1]);
            $stageNegotiation = CrmStage::firstOrCreate(['pipeline_id' => $pipeline->id, 'name' => 'Negotiation'], ['position' => 2]);

            // 2. CRM Leads & Deals
            CrmLead::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stageLead->id,
                'name' => 'Apex Cloud Systems',
                'email' => 'partnerships@apexcloud.io',
                'phone' => '+1 (555) 234-5678',
                'company' => 'Apex Cloud Inc.',
                'status' => 'qualified',
                'subject' => '[DEMO] Annual Enterprise License',
            ]);

            CrmDeal::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stageNegotiation->id,
                'name' => '[DEMO] Quantum Robotics Expansion',
                'price' => 48000.00,
                'status' => 'open',
            ]);

            // 3. Inventory Products
            $category = ProductServiceCategory::firstOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => 'Software & Hardware',
            ], [
                'type' => 'product',
                'color' => '#4f46e5',
            ]);

            $item1 = ProductServiceItem::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'category_id' => $category->id,
                'name' => '[DEMO] Enterprise Edge Gateway',
                'sku' => 'DEMO-GW-100',
                'type' => 'product',
                'quantity' => 4, // Low stock -> triggers signal
                'sale_price' => 2500.00,
                'purchase_price' => 1400.00,
            ]);

            $item2 = ProductServiceItem::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'category_id' => $category->id,
                'name' => '[DEMO] Business OS Annual Subscription',
                'sku' => 'DEMO-SaaS-01',
                'type' => 'service',
                'quantity' => 100,
                'sale_price' => 12000.00,
                'purchase_price' => 0.00,
            ]);

            // 4. Invoices
            $invOverdue = SalesInvoice::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'invoice_id' => 'INV-DEMO-901',
                'issue_date' => now()->subDays(20)->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'total_amount' => 18500.00,
                'status' => 'sent',
            ]);

            SalesInvoiceItem::create([
                'invoice_id' => $invOverdue->id,
                'item_id' => $item1->id,
                'quantity' => 2,
                'price' => 2500.00,
                'total' => 5000.00,
            ]);

            // 5. Communications
            $commAccount = CommunicationAccount::firstOrCreate([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'provider' => 'internal',
                'external_account_id' => 'demo_support_bot',
            ], [
                'display_name' => 'Demo Support Inbox',
                'status' => 'connected',
            ]);

            $conversation = CommunicationConversation::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'account_id' => $commAccount->id,
                'provider' => 'internal',
                'external_thread_id' => 'demo_thread_' . uniqid(),
                'subject' => '[DEMO] URGENT: Contract Clarification for Q3 Delivery',
                'participant_name' => 'Sarah Connor (Cyberdyne Systems)',
                'participant_identifier' => 'sconnor@cyberdyne.com',
                'last_message_preview' => 'Can we finalize the signed SLA agreement before end of day tomorrow?',
                'last_message_at' => now()->subMinutes(15),
                'priority_score' => 88,
                'status' => 'open',
                'unread_count' => 1,
            ]);

            CommunicationMessage::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'conversation_id' => $conversation->id,
                'provider_message_id' => 'msg_demo_' . uniqid(),
                'direction' => 'inbound',
                'sender_name' => 'Sarah Connor',
                'sender_identifier' => 'sconnor@cyberdyne.com',
                'body_text' => 'Can we finalize the signed SLA agreement before end of day tomorrow?',
                'delivery_status' => 'delivered',
                'sent_at' => now()->subMinutes(15),
            ]);

            // 6. Project & Tasks
            $project = TasklyProject::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'name' => '[DEMO] Client ERP Onboarding',
                'status' => 'in_progress',
                'created_by' => $user->id,
            ]);

            $taskStage = \App\Models\TasklyStage::firstOrCreate([
                'project_id' => $project->id,
                'name' => 'To Do',
            ], ['position' => 0]);

            TasklyTask::create([
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'project_id' => $project->id,
                'stage_id' => $taskStage->id,
                'title' => '[DEMO] Finalize API Data Mapping & Cutover',
                'priority' => 'high',
                'due_date' => now()->subDays(2)->toDateString(), // Overdue task -> triggers signal
            ]);

            return [
                'leads_count' => 1,
                'deals_count' => 1,
                'products_count' => 2,
                'invoices_count' => 1,
                'conversations_count' => 1,
                'tasks_count' => 1,
            ];
        });
    }

    /**
     * Safely clears all demonstration records from the workspace.
     */
    public function resetDemoData(Workspace $workspace): int
    {
        $wsId = $workspace->id;

        return DB::transaction(function () use ($wsId) {
            $deleted = 0;
            $deleted += CrmLead::where('workspace_id', $wsId)->where('subject', 'like', '[DEMO]%')->delete();
            $deleted += CrmDeal::where('workspace_id', $wsId)->where('name', 'like', '[DEMO]%')->delete();
            $deleted += ProductServiceItem::where('workspace_id', $wsId)->where('name', 'like', '[DEMO]%')->delete();
            $deleted += SalesInvoice::where('workspace_id', $wsId)->where('invoice_id', 'like', 'INV-DEMO-%')->delete();
            $deleted += CommunicationConversation::where('workspace_id', $wsId)->where('subject', 'like', '[DEMO]%')->delete();
            $deleted += TasklyProject::where('workspace_id', $wsId)->where('name', 'like', '[DEMO]%')->delete();

            return $deleted;
        });
    }
}
