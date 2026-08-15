<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\RiskLevel;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\MrFoxActionProposal;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MrFoxPostgresApprovalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_approval_execution_safely_executes_exactly_once_with_row_locks(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Concurrency Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'crm']);

        $pipe = CrmPipeline::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Pipeline Concurrent']);
        CrmStage::create(['pipeline_id' => $pipe->id, 'name' => 'Stage 1', 'position' => 0]);

        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            ['name' => 'Atomic Concurrency Lead', 'email' => 'concurrency@example.com'],
            'Proposed lead creation under concurrency',
            RiskLevel::HIGH
        );

        $results = [];

        // Simulate two race condition execution calls within transactions
        $results[] = $approvalService->approveAndExecute($proposal->id, $user, $context);
        $results[] = $approvalService->approveAndExecute($proposal->id, $user, $context);

        $successCount = count(array_filter($results, fn ($r) => $r->success));
        $failureCount = count(array_filter($results, fn ($r) => ! $r->success));

        // Exactly one call must succeed and one must be rejected
        $this->assertEquals(1, $successCount, 'Exactly one concurrent execution must succeed');
        $this->assertEquals(1, $failureCount, 'Subsequent execution must fail cleanly');

        // Verify that only 1 lead was created in database
        $this->assertEquals(1, CrmLead::where('name', 'Atomic Concurrency Lead')->count());

        $proposal->refresh();
        $this->assertEquals('executed', $proposal->status);
    }
}
