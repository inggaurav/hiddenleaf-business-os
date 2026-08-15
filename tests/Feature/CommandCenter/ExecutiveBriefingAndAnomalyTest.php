<?php

namespace Tests\Feature\CommandCenter;

use App\Domain\CommandCenter\Anomalies\AnomalyDetectionEngine;
use App\Domain\CommandCenter\Briefings\ExecutiveBriefingService;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveBriefingAndAnomalyTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_briefing_generation_and_anomalies(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Plan Briefing', 'modules' => ['account', 'crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        // Create Invoices to test anomaly
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'INV-BRIEF-01',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'total_amount' => 45000,
            'status' => 'sent',
        ]);

        $briefingService = app(ExecutiveBriefingService::class);
        $briefing = $briefingService->generateBriefing($user, $ws, 'today');

        $this->assertEquals('today', $briefing->period);
        $this->assertNotEmpty($briefing->summaryHeadline);
        $this->assertArrayHasKey('total_sales', $briefing->financialSnapshot);

        // Test Anomaly Detection
        $anomalyEngine = app(AnomalyDetectionEngine::class);
        $anomalies = $anomalyEngine->detectAnomalies($user, $ws);

        $this->assertNotEmpty($anomalies);
        $this->assertEquals('high', $anomalies[0]->confidence);
    }
}
