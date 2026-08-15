<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\RiskLevel;
use App\Domain\MrFox\Tools\CrmCreateLeadTool;
use App\Models\CrmLead;
use App\Models\MrFoxActionProposal;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxActionApprovalTest extends TestCase
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
        $plan = Plan::create(['name' => 'Apex Plan', 'modules' => ['crm', 'account', 'taskly'], 'status' => true, 'created_by' => $this->user->id]);
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id, 'name' => 'Apex Agency']);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'name' => 'Apex Workspace']);

        $this->org->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'crm']);

        $pipeline = \App\Models\CrmPipeline::create([
            'organization_id' => $this->org->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Apex Pipeline',
            'is_default' => true,
        ]);
        \App\Models\CrmStage::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Inbound',
            'position' => 0,
            'probability' => 20,
        ]);
    }

    public function test_safe_action_tools_execute_directly(): void
    {
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace);

        $tool = new CrmCreateLeadTool();
        $result = $tool->execute($context, [
            'name' => 'Prospective Client Beta',
            'email' => 'beta@example.com',
            'company_name' => 'Beta Ventures',
            'value' => 25000,
        ]);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('crm_leads', [
            'workspace_id' => $this->workspace->id,
            'name' => 'Prospective Client Beta',
            'email' => 'beta@example.com',
        ]);
    }

    public function test_high_risk_action_proposals_require_approval_and_execute_immutably(): void
    {
        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace, 'conv_123');

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            [
                'name' => 'High Value Enterprise Client',
                'email' => 'enterprise@bigcorp.com',
                'value' => 250000,
            ],
            'Create Enterprise Lead with value $250,000',
            RiskLevel::HIGH
        );

        $this->assertEquals('pending', $proposal->status);
        $this->assertDatabaseMissing('crm_leads', [
            'name' => 'High Value Enterprise Client',
        ]);

        // Approve proposal via API
        $response = $this->actingAs($this->user)->postJson("/api/v1/mr-fox/actions/{$proposal->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('crm_leads', [
            'workspace_id' => $this->workspace->id,
            'name' => 'High Value Enterprise Client',
        ]);

        $proposal->refresh();
        $this->assertEquals('executed', $proposal->status);
        $this->assertEquals($this->user->id, $proposal->approved_by);
    }

    public function test_rejection_cancels_proposal_without_execution(): void
    {
        $approvalService = app(ActionApprovalService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($this->user, $this->workspace);

        $proposal = $approvalService->createProposal(
            $context,
            'crm.create.lead',
            ['name' => 'Rejected Prospect'],
            'Proposed Lead Creation',
            RiskLevel::HIGH
        );

        $response = $this->actingAs($this->user)->postJson("/api/v1/mr-fox/actions/{$proposal->id}/reject");
        $response->assertStatus(200);

        $proposal->refresh();
        $this->assertEquals('rejected', $proposal->status);
        $this->assertDatabaseMissing('crm_leads', ['name' => 'Rejected Prospect']);
    }
}
