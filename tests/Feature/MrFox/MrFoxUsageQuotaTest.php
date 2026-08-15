<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Observability\MrFoxUsageService;
use App\Models\MrFoxUsageRecord;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxUsageQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_records_enforce_non_negative_tokens_and_track_monthly_usage(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Usage Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $usageService = app(MrFoxUsageService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        // Record usage with negative input attempt
        $record = $usageService->recordUsage($context, 'openai', 'gpt-4o', -50, 120);

        $this->assertNotNull($record);
        $this->assertEquals(0, $record->input_tokens); // normalized to 0
        $this->assertEquals(120, $record->output_tokens);

        $monthly = $usageService->getMonthlyUsage($workspace->id);
        $this->assertEquals(120, $monthly['total_tokens']);
        $this->assertEquals(1, $monthly['requests_count']);
        $this->assertFalse($usageService->hasExceededMonthlyQuota($workspace->id));
    }
}
