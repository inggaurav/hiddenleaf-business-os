<?php

namespace Tests\Feature\CommandCenter;

use App\Domain\CommandCenter\Health\BusinessHealthService;
use App\Domain\CommandCenter\Signals\SignalDetector;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessHealthAndSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_business_health_scoring_and_signals(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan Core', 'modules' => ['account', 'crm', 'taskly', 'productservice'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        // 1. Initial State: Zero overdue items -> High health score
        $healthService = app(BusinessHealthService::class);
        $initialHealth = $healthService->evaluateHealth($user, $ws);
        $this->assertEquals(100, $initialHealth['overall']->score);
        $this->assertEquals('healthy', $initialHealth['overall']->status);

        // 2. Introduce Overdue Invoice -> Receivables score drops
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'INV-901',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'total_amount' => 15000,
            'status' => 'sent',
        ]);

        $healthAfterInvoice = $healthService->evaluateHealth($user, $ws);
        $this->assertLessThan(100, $healthAfterInvoice['dimensions']['receivables']->score);
        $this->assertContains($healthAfterInvoice['dimensions']['receivables']->status, ['warning', 'critical', 'watch']);

        // 3. Introduce Urgent Communication -> Comms score drops
        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'provider' => 'internal',
            'external_account_id' => 'bot_1',
            'display_name' => 'Support',
        ]);

        CommunicationConversation::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'account_id' => $account->id,
            'provider' => 'internal',
            'external_thread_id' => 'thread_urgent_1',
            'subject' => 'URGENT: Contract escalation',
            'participant_name' => 'Enterprise Buyer',
            'priority_score' => 95,
            'status' => 'open',
        ]);

        $healthAfterComms = $healthService->evaluateHealth($user, $ws);
        $this->assertLessThan(100, $healthAfterComms['dimensions']['communications']->score);

        // 4. Test Signals Detection
        $detector = app(SignalDetector::class);
        $signals = $detector->detectSignals($user, $ws);
        $signalIds = array_map(fn ($s) => $s->id, $signals);

        $this->assertContains('finance.overdue_receivables', $signalIds);
        $this->assertContains('communications.urgent', $signalIds);
    }

    public function test_role_aware_health_score_privacy(): void
    {
        $this->seed();
        // Regular user without financial or HR permissions
        $owner = User::factory()->create(['role' => 'company']);
        $user = User::factory()->create(['role' => 'member']);
        $plan = Plan::create(['name' => 'Plan M', 'modules' => ['crm', 'taskly'], 'status' => true, 'created_by' => $owner->id]);
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);
        $org->members()->attach($owner, ['role' => 'owner']);
        $org->members()->attach($user, ['role' => 'member']);
        $ws->members()->attach($user);

        // Create confidential finance invoice in database
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'INV-CONFIDENTIAL',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'total_amount' => 500000,
            'status' => 'sent',
        ]);

        $healthService = app(BusinessHealthService::class);
        $health = $healthService->evaluateHealth($user, $ws);

        // Receivables dimension MUST be completely omitted for unauthorized user
        $this->assertArrayNotHasKey('receivables', $health['dimensions']);
        $this->assertArrayNotHasKey('payables', $health['dimensions']);
        $this->assertArrayNotHasKey('cash', $health['dimensions']);
    }
}
