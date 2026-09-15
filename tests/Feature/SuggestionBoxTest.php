<?php

namespace Tests\Feature;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use HiddenLeaf\SuggestionBox\Domain\MrFox\Tools\SuggestionSummaryTool;
use HiddenLeaf\SuggestionBox\Domain\Services\SuggestionService;
use HiddenLeaf\SuggestionBox\Models\Suggestion;
use HiddenLeaf\SuggestionBox\Models\SuggestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionBoxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $memberUser;
    private Organization $organization;
    private Workspace $workspace;
    private Addon $addon;
    private SuggestionService $suggestionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $this->memberUser = User::factory()->create(['role' => 'employee']);

        $plan = Plan::create([
            'name' => 'Suggestion Box Plan',
            'modules' => ['suggestion-box'],
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
        $this->organization->members()->attach($this->memberUser, ['role' => 'member']);

        $this->workspace->members()->attach($this->user);
        $this->workspace->members()->attach($this->memberUser);

        $this->addon = Addon::firstOrCreate(
            ['alias' => 'suggestion-box'],
            [
                'addon_id' => 'hiddenleaf-suggestion-box',
                'name' => 'Suggestion Box',
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

        UserActiveModule::firstOrCreate([
            'workspace_id' => $this->workspace->id,
            'module_name' => 'suggestion-box',
        ]);

        $this->suggestionService = app(SuggestionService::class);
    }

    public function test_workspace_isolation_and_scoping(): void
    {
        $otherWorkspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $cat1 = $this->suggestionService->createCategory($this->workspace, ['name' => 'HR Operations'], $this->user);
        $cat2 = $this->suggestionService->createCategory($otherWorkspace, ['name' => 'Engineering'], $this->user);

        $sug1 = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Ergonomic chairs for WS1',
            'description' => 'Need better seating',
            'category_id' => $cat1->id,
        ], $this->memberUser);

        $sug2 = $this->suggestionService->createSuggestion($otherWorkspace, [
            'title' => 'Dual monitors for WS2',
            'description' => 'Need larger screens',
            'category_id' => $cat2->id,
        ], $this->user);

        $ws1Suggestions = Suggestion::forWorkspace($this->workspace->organization_id, $this->workspace->id)->get();
        $this->assertTrue($ws1Suggestions->contains('id', $sug1->id));
        $this->assertFalse($ws1Suggestions->contains('id', $sug2->id));

        $ws1Categories = SuggestionCategory::forWorkspace($this->workspace->organization_id, $this->workspace->id)->get();
        $this->assertTrue($ws1Categories->contains('id', $cat1->id));
        $this->assertFalse($ws1Categories->contains('id', $cat2->id));
    }

    public function test_suggestion_creation_anonymous_and_public(): void
    {
        // Public suggestion
        $publicSug = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Friday Tech Talks',
            'description' => 'Organize weekly knowledge sharing sessions',
            'is_anonymous' => false,
        ], $this->memberUser);

        $this->assertDatabaseHas('suggestions', [
            'id' => $publicSug->id,
            'title' => 'Friday Tech Talks',
            'is_anonymous' => false,
            'user_id' => $this->memberUser->id,
            'status' => 'new',
        ]);

        // Anonymous suggestion
        $anonSug = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Anonymous Feedback on Pantry',
            'description' => 'More healthy snacks please',
            'is_anonymous' => true,
        ], $this->memberUser);

        $this->assertDatabaseHas('suggestions', [
            'id' => $anonSug->id,
            'title' => 'Anonymous Feedback on Pantry',
            'is_anonymous' => true,
        ]);
        $this->assertTrue((bool) $anonSug->fresh()->is_anonymous);
    }

    public function test_voting_flow_and_self_voting_prevention(): void
    {
        $suggestion = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Remote Work Policy',
            'description' => 'Allow flexible hours',
        ], $this->memberUser);

        // Creator cannot vote on own suggestion
        $this->expectException(\InvalidArgumentException::class);
        $this->suggestionService->vote($suggestion, $this->memberUser);
    }

    public function test_voting_toggle_by_other_user(): void
    {
        $suggestion = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Pet Friendly Office',
            'description' => 'Bring dogs to work on Thursdays',
        ], $this->memberUser);

        $this->assertEquals(0, $suggestion->votes_count);

        // Admin votes
        $voteResult = $this->suggestionService->vote($suggestion, $this->user);
        $this->assertTrue($voteResult['voted']);
        $this->assertEquals(1, $voteResult['votes_count']);
        $this->assertEquals(1, $suggestion->fresh()->votes_count);
        $this->assertTrue($suggestion->fresh()->isVotedBy($this->user));

        // Admin toggles vote off
        $unvoteResult = $this->suggestionService->vote($suggestion, $this->user);
        $this->assertFalse($unvoteResult['voted']);
        $this->assertEquals(0, $unvoteResult['votes_count']);
        $this->assertEquals(0, $suggestion->fresh()->votes_count);
        $this->assertFalse($suggestion->fresh()->isVotedBy($this->user));
    }

    public function test_unique_view_recording(): void
    {
        $suggestion = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'New Coffee Machine',
            'description' => 'Espresso bar in kitchen',
        ], $this->user);

        $this->assertEquals(0, $suggestion->views_count);

        // Member views once
        $this->suggestionService->recordView($suggestion, $this->memberUser);
        $this->assertEquals(1, $suggestion->fresh()->views_count);

        // Member views again - should not increment
        $this->suggestionService->recordView($suggestion, $this->memberUser);
        $this->assertEquals(1, $suggestion->fresh()->views_count);
    }

    public function test_suggestion_status_lifecycle_and_history(): void
    {
        $suggestion = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Annual Hackathon',
            'description' => 'Host 24hr internal hackathon',
        ], $this->memberUser);

        $this->assertEquals('new', $suggestion->status);

        // Admin moves to under_review
        $this->suggestionService->respond($suggestion, 'under_review', 'Evaluating with engineering leads', $this->user);
        $this->assertEquals('under_review', $suggestion->fresh()->status);
        $this->assertEquals('Evaluating with engineering leads', $suggestion->fresh()->admin_response);
        $this->assertEquals($this->user->id, $suggestion->fresh()->responded_by);

        // Admin accepts suggestion
        $this->suggestionService->respond($suggestion, 'accepted', 'Approved for Q4 schedule', $this->user);
        $this->assertEquals('accepted', $suggestion->fresh()->status);

        // Check status history audit table
        $this->assertDatabaseHas('suggestion_status_histories', [
            'suggestion_id' => $suggestion->id,
            'old_status' => 'new',
            'new_status' => 'under_review',
        ]);
        $this->assertDatabaseHas('suggestion_status_histories', [
            'suggestion_id' => $suggestion->id,
            'old_status' => 'under_review',
            'new_status' => 'accepted',
        ]);
    }

    public function test_category_crud(): void
    {
        $category = $this->suggestionService->createCategory($this->workspace, [
            'name' => 'Facilities',
            'color' => '#10B981',
            'description' => 'Office infrastructure and snacks',
        ], $this->user);

        $this->assertDatabaseHas('suggestion_categories', [
            'id' => $category->id,
            'name' => 'Facilities',
            'color' => '#10B981',
        ]);

        $this->suggestionService->updateCategory($category, [
            'name' => 'Workplace Experience',
            'color' => '#059669',
        ], $this->user);

        $this->assertEquals('Workplace Experience', $category->fresh()->name);
        $this->assertEquals('#059669', $category->fresh()->color);

        $this->suggestionService->deleteCategory($category, $this->user);
        $this->assertDatabaseMissing('suggestion_categories', ['id' => $category->id]);
    }

    public function test_http_board_detail_and_vote_endpoints(): void
    {
        $category = $this->suggestionService->createCategory($this->workspace, [
            'name' => 'General',
            'color' => '#6366F1',
        ], $this->user);

        $suggestion = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Book Club',
            'description' => 'Monthly company book club',
            'category_id' => $category->id,
        ], $this->user);

        // Member visits board
        $boardRes = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/suggestion-box');

        $boardRes->assertStatus(200);

        // Member visits detail page (triggers view count)
        $detailRes = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get("/suggestion-box/suggestions/{$suggestion->id}");

        $detailRes->assertStatus(200);
        $this->assertEquals(1, $suggestion->fresh()->views_count);

        // Member votes via POST
        $voteRes = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->postJson("/suggestion-box/suggestions/{$suggestion->id}/vote");

        $voteRes->assertStatus(200);
        $voteRes->assertJson(['voted' => true, 'votes_count' => 1]);
        $this->assertEquals(1, $suggestion->fresh()->votes_count);
    }

    public function test_mrfox_suggestion_box_summary_tool(): void
    {
        $category = $this->suggestionService->createCategory($this->workspace, [
            'name' => 'Workplace',
            'color' => '#3B82F6',
        ], $this->user);

        $sug1 = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Gym Membership Subsidy',
            'description' => 'Provide wellness benefits',
            'category_id' => $category->id,
        ], $this->user);

        $sug2 = $this->suggestionService->createSuggestion($this->workspace, [
            'title' => 'Transit Passes',
            'description' => 'Discounted public transit',
            'category_id' => $category->id,
        ], $this->memberUser);

        // Give sug1 a vote
        $this->suggestionService->vote($sug1, $this->memberUser);

        $tool = new SuggestionSummaryTool();
        $this->assertEquals('suggestion_box.summary', $tool->name());
        $this->assertEquals('suggestion-box.view', $tool->requiredPermission());
        $this->assertEquals('suggestion-box', $tool->requiredModule());

        $context = new ToolContext(
            user: $this->memberUser,
            organization: $this->organization,
            workspace: $this->workspace
        );

        $result = $tool->execute($context, []);
        $this->assertTrue($result->success);

        $data = $result->data;
        $this->assertEquals(2, $data['total_suggestions']);
        $this->assertEquals(2, $data['new_count']);
        $this->assertNotEmpty($data['top_voted']);
        $this->assertEquals('Gym Membership Subsidy', $data['top_voted'][0]['title']);
        $this->assertEquals(1, $data['top_voted'][0]['votes_count']);
    }
}
