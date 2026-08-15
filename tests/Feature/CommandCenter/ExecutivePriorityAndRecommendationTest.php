<?php

namespace Tests\Feature\CommandCenter;

use App\Domain\CommandCenter\Priorities\BusinessPriorityService;
use App\Domain\CommandCenter\Recommendations\ExecutiveRecommendationService;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutivePriorityAndRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_priority_ranking_and_recommendations(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan Core', 'modules' => ['account', 'crm', 'productservice', 'communications'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        // 1. Critical Overdue Invoice
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'INV-PRIORITY-01',
            'issue_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'total_amount' => 50000,
            'status' => 'sent',
        ]);

        // 2. Urgent Message
        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'provider' => 'internal',
            'external_account_id' => 'bot_01',
            'display_name' => 'Support',
        ]);

        CommunicationConversation::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_id' => $account->id,
            'provider' => 'internal',
            'external_thread_id' => 'thread_vip_01',
            'subject' => 'URGENT Account Review',
            'participant_name' => 'VIP Client',
            'priority_score' => 90,
            'is_resolved' => false,
        ]);

        // 3. Low Stock Product
        ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Server Hardware Module',
            'sku' => 'SRV-001',
            'type' => 'product',
            'quantity' => 1,
            'sale_price' => 1200,
        ]);

        $priorityService = app(BusinessPriorityService::class);
        $priorities = $priorityService->getPriorities($user, $ws);

        $this->assertNotEmpty($priorities);
        $this->assertEquals(1, $priorities[0]->rank);
        // The highest priority has rank 1 and carries actionable pathways
        $this->assertNotEmpty($priorities[0]->actions);

        // Test Recommendations
        $recService = app(ExecutiveRecommendationService::class);
        $recs = $recService->getRecommendations($user, $ws);

        $this->assertNotEmpty($recs);
        $this->assertNotEmpty($recs[0]->fingerprint);
        $this->assertEquals('new', $recs[0]->status);
    }
}
