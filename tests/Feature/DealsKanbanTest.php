<?php

namespace Tests\Feature;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\Addon;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use HiddenLeaf\CrmDealsKanban\Domain\MrFox\Tools\CrmDealWonLostTool;
use HiddenLeaf\CrmDealsKanban\Domain\Services\DealService;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealActivity;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealApproval;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealsKanbanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private CrmPipeline $pipeline;
    private CrmStage $stage1;
    private CrmStage $stage2;
    private CrmStage $stageWon;
    private CrmStage $stageLost;
    private Addon $addon;
    private DealService $dealService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'CRM Deals Plan',
            'modules' => ['crm-deals-kanban', 'crm', 'lead'],
            'status' => true,
            'created_by' => $this->user->id,
        ]);
        $this->organization = Organization::factory()->create([
            'owner_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);
        $this->workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        $this->addon = Addon::firstOrCreate(
            ['alias' => 'crm-deals-kanban'],
            [
                'addon_id' => 'hiddenleaf-crm-deals-kanban',
                'name' => 'CRM Deals Kanban',
                'status' => 'installed',
                'version' => '1.0.0',
                'minimum_core' => '1.0.0',
                'dependencies' => [],
                'manifest' => [],
            ]
        );

        WorkspaceAddon::firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'addon_id' => $this->addon->id],
            ['is_active' => true]
        );
        UserActiveModule::firstOrCreate(['workspace_id' => $this->workspace->id, 'module_name' => 'crm-deals-kanban']);

        $this->pipeline = CrmPipeline::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Main Sales Pipeline',
            'is_default' => true,
        ]);

        $this->stage1 = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Qualified',
            'position' => 0,
            'probability' => 25,
        ]);

        $this->stage2 = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Proposal Sent',
            'position' => 1,
            'probability' => 60,
        ]);

        $this->stageWon = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Won',
            'position' => 2,
            'probability' => 100,
            'outcome' => 'won',
            'is_closed' => true,
        ]);

        $this->stageLost = CrmStage::create([
            'pipeline_id' => $this->pipeline->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Lost',
            'position' => 3,
            'probability' => 0,
            'outcome' => 'lost',
            'is_closed' => true,
        ]);

        $this->dealService = app(DealService::class);
    }

    public function test_kanban_board_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->get('/crm/kanban');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CRM/Kanban')
            ->has('stages')
            ->has('pipelines')
        );
    }

    public function test_deal_creation_via_http(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post('/crm/deals', [
                'name' => 'Acme Cloud Migration',
                'value' => 150000.00,
                'pipeline_id' => $this->pipeline->id,
                'stage_id' => $this->stage1->id,
                'source' => 'inbound',
                'expected_close_date' => '2026-12-31',
            ]);

        $response->assertSessionHasNoErrors();
        $deal = CrmDeal::where('name', 'Acme Cloud Migration')->firstOrFail();
        $this->assertEquals(150000.00, (float) $deal->value);
        $this->assertEquals($this->stage1->id, $deal->stage_id);
        $this->assertEquals(25, $deal->probability);
    }

    public function test_move_deal_between_stages(): void
    {
        $deal = $this->dealService->createDeal($this->workspace, [
            'name' => 'Fintech Analytics Contract',
            'value' => 500000,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/move", [
                'stage_id' => $this->stage2->id,
                'pipeline_id' => $this->pipeline->id,
            ]);

        $response->assertSessionHasNoErrors();
        $deal->refresh();
        $this->assertEquals($this->stage2->id, $deal->stage_id);
        $this->assertEquals(60, $deal->probability);
    }

    public function test_reorder_deals_in_stage(): void
    {
        $deal1 = $this->dealService->createDeal($this->workspace, [
            'name' => 'Deal 1',
            'value' => 10000,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        $deal2 = $this->dealService->createDeal($this->workspace, [
            'name' => 'Deal 2',
            'value' => 20000,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        $response = $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post('/crm/deals/reorder', [
                'stage_id' => $this->stage1->id,
                'ordered_ids' => [$deal2->id, $deal1->id],
            ]);

        $response->assertSessionHasNoErrors();
        $deal1->refresh();
        $deal2->refresh();
        $this->assertEquals(1, $deal1->position);
        $this->assertEquals(0, $deal2->position);
    }

    public function test_mark_deal_as_won_and_lost(): void
    {
        $deal = $this->dealService->createDeal($this->workspace, [
            'name' => 'Victory Deal',
            'value' => 250000,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        // Mark Won
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/won")
            ->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertEquals('won', $deal->status);
        $this->assertEquals(100, $deal->probability);
        $this->assertNotNull($deal->actual_close_date);

        // Mark Lost
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/lost", ['lost_reason' => 'Budget constraints'])
            ->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertEquals('lost', $deal->status);
        $this->assertEquals(0, $deal->probability);
        $this->assertEquals('Budget constraints', $deal->lost_reason);
    }

    public function test_log_activity_and_attach_file(): void
    {
        Storage::fake('public');

        $deal = $this->dealService->createDeal($this->workspace, [
            'name' => 'Activity Test Deal',
            'value' => 50000,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        // Log Activity
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/activities", [
                'type' => 'call',
                'subject' => 'Initial Discovery Call',
                'body' => 'Discussed business requirements and budget.',
            ])
            ->assertSessionHasNoErrors();

        $activity = CrmDealActivity::where('deal_id', $deal->id)->firstOrFail();
        $this->assertEquals('call', $activity->type);
        $this->assertEquals('Initial Discovery Call', $activity->subject);

        // Attach File
        $fakeFile = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/files", [
                'file' => $fakeFile,
            ])
            ->assertSessionHasNoErrors();

        $dealFile = CrmDealFile::where('deal_id', $deal->id)->firstOrFail();
        $this->assertEquals('contract.pdf', $dealFile->file_name);
    }

    public function test_discount_approval_workflow(): void
    {
        $deal = $this->dealService->createDeal($this->workspace, [
            'name' => 'Discounted Deal',
            'value' => 100000,
            'stage_id' => $this->stage1->id,
        ], $this->user);

        // Request Approval
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/{$deal->id}/approvals", [
                'discount_percentage' => 25.5,
            ])
            ->assertSessionHasNoErrors();

        $approval = CrmDealApproval::where('deal_id', $deal->id)->firstOrFail();
        $this->assertEquals(25.5, (float) $approval->discount_percentage);
        $this->assertEquals('pending', $approval->status);

        // Approve Decision
        $this->actingAs($this->user)
            ->withSession([
                'active_organization_id' => $this->organization->id,
                'active_workspace_id' => $this->workspace->id,
            ])
            ->post("/crm/deals/approvals/{$approval->id}/decision", [
                'decision' => 'approved',
                'notes' => 'Approved for Q3 special promotion',
            ])
            ->assertSessionHasNoErrors();

        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
        $this->assertNotNull($approval->decided_at);
    }

    public function test_deal_service_methods_directly(): void
    {
        Storage::fake('public');

        // 1. createDeal with ($data, $ws, $actor) signature
        $deal = $this->dealService->createDeal([
            'name' => 'Direct Signature Deal',
            'value' => 85000,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage1->id,
        ], $this->workspace, $this->user);
        $this->assertInstanceOf(CrmDeal::class, $deal);

        // 2. moveToPipeline
        $moved = $this->dealService->moveToPipeline($deal, $this->pipeline, $this->stage2, $this->user);
        $this->assertEquals($this->stage2->id, $moved->stage_id);

        // 3. reorderInStage with (CrmDeal, position, CrmStage, actor)
        $this->dealService->reorderInStage($deal, 5, $this->stage2, $this->user);
        $deal->refresh();
        $this->assertEquals(5, $deal->position);

        // 4. logActivity with ($deal, $data, $actor)
        $activity = $this->dealService->logActivity($deal, [
            'type' => 'meeting',
            'subject' => 'Architecture Sync',
            'description' => 'Discussed cloud topology',
        ], $this->user);
        $this->assertInstanceOf(CrmDealActivity::class, $activity);

        // 5. attachFile
        $file = UploadedFile::fake()->create('spec.pdf', 50);
        $dealFile = $this->dealService->attachFile($deal, $file, $this->user);
        $this->assertInstanceOf(CrmDealFile::class, $dealFile);

        // 6. requestApproval with ($deal, float, $actor)
        $approval = $this->dealService->requestApproval($deal, 15.0, $this->user);
        $this->assertInstanceOf(CrmDealApproval::class, $approval);

        // 7. decideApproval with boolean true
        $decided = $this->dealService->decideApproval($approval, true, 'Approved by VP', $this->user);
        $this->assertEquals('approved', $decided->status);

        // 8. markWon
        $wonDeal = $this->dealService->markWon($deal, $this->user);
        $this->assertEquals('won', $wonDeal->status);

        // 9. markLost
        $lostDeal = $this->dealService->markLost($deal, 'Competitor offer', $this->user);
        $this->assertEquals('lost', $lostDeal->status);
    }

    public function test_crm_deal_won_lost_mrfox_tool(): void
    {
        // Create 2 won deals and 1 lost deal this month
        $dealWon1 = $this->dealService->createDeal($this->workspace, [
            'name' => 'Won 1',
            'value' => 100000,
            'stage_id' => $this->stage1->id,
        ], $this->user);
        $this->dealService->markWon($dealWon1, $this->user);

        $dealWon2 = $this->dealService->createDeal($this->workspace, [
            'name' => 'Won 2',
            'value' => 200000,
            'stage_id' => $this->stage1->id,
        ], $this->user);
        $this->dealService->markWon($dealWon2, $this->user);

        $dealLost = $this->dealService->createDeal($this->workspace, [
            'name' => 'Lost 1',
            'value' => 50000,
            'stage_id' => $this->stage1->id,
        ], $this->user);
        $this->dealService->markLost($dealLost, 'Price too high', $this->user);

        $tool = new CrmDealWonLostTool();
        $this->assertEquals('crm_deals_won_lost', $tool->name());
        $this->assertNotEmpty($tool->description());

        $context = new ToolContext(
            user: $this->user,
            organization: $this->organization,
            workspace: $this->workspace
        );

        $result = $tool->execute($context, []);
        $this->assertTrue($result->success);

        $data = $result->data;
        $this->assertEquals(2, $data['won_mtd_count']);
        $this->assertEquals(300000.0, (float) $data['won_mtd_value']);
        $this->assertEquals(1, $data['lost_mtd_count']);
        $this->assertEquals(50000.0, (float) $data['lost_mtd_value']);
        $this->assertEquals(66.7, $data['win_rate']);
    }
}
