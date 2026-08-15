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
use Tests\TestCase;

class MrFoxApprovalSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Full Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $this->user->id]);
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->org->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'crm']);

        $pipe = CrmPipeline::create(['organization_id' => $this->org->id, 'workspace_id' => $this->workspace->id, 'name' => 'Main Pipeline']);
        CrmStage::create(['pipeline_id' => $pipe->id, 'name' => 'Inbound', 'position' => 0]);
    }

    public function test_tampered_payload_is_blocked_by_hash_verification(): void
    {
        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace);

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            ['name' => 'Legitimate Lead Name', 'email' => 'legit@example.com'],
            'Proposed lead creation',
            RiskLevel::HIGH
        );

        // Adversary alters payload in database directly
        $proposal->payload = ['name' => 'TAMPERED MALICIOUS LEAD', 'email' => 'hacked@example.com'];
        $proposal->saveQuietly();

        // Attempt execution
        $result = $approvalService->approveAndExecute($proposal->id, $this->user, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('integrity check failed', $result->error);

        $proposal->refresh();
        $this->assertEquals('failed', $proposal->status);
        $this->assertDatabaseMissing('crm_leads', ['name' => 'TAMPERED MALICIOUS LEAD']);
    }

    public function test_expired_proposal_cannot_be_executed(): void
    {
        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace);

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            ['name' => 'Expired Prospect'],
            'Proposed lead',
            RiskLevel::HIGH
        );

        // Set expires_at to the past
        $proposal->update(['expires_at' => now()->subHours(2)]);

        $result = $approvalService->approveAndExecute($proposal->id, $this->user, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('expired', $result->error);

        $proposal->refresh();
        $this->assertEquals('expired', $proposal->status);
        $this->assertDatabaseMissing('crm_leads', ['name' => 'Expired Prospect']);
    }

    public function test_double_execution_is_prevented(): void
    {
        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace);

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            ['name' => 'Single Execution Lead'],
            'Proposed lead',
            RiskLevel::HIGH
        );

        // First execution succeeds
        $res1 = $approvalService->approveAndExecute($proposal->id, $this->user, $context);
        $this->assertTrue($res1->success);

        // Second execution attempt is rejected
        $res2 = $approvalService->approveAndExecute($proposal->id, $this->user, $context);
        $this->assertFalse($res2->success);
        $this->assertStringContainsString('Current status: executed', $res2->error);

        // Verify only 1 lead was created in DB
        $this->assertEquals(1, CrmLead::where('name', 'Single Execution Lead')->count());
    }
}
