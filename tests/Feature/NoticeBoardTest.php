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
use HiddenLeaf\NoticeBoard\Domain\MrFox\Tools\NoticeBoardSummaryTool;
use HiddenLeaf\NoticeBoard\Domain\Services\NoticeService;
use HiddenLeaf\NoticeBoard\Models\Notice;
use HiddenLeaf\NoticeBoard\Models\NoticeComment;
use HiddenLeaf\NoticeBoard\Models\NoticeRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $memberUser;
    private Organization $organization;
    private Workspace $workspace;
    private Addon $addon;
    private NoticeService $noticeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $this->memberUser = User::factory()->create(['role' => 'employee']);

        $plan = Plan::create([
            'name' => 'Notice Board Plan',
            'modules' => ['notice-board'],
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
            ['alias' => 'notice-board'],
            [
                'addon_id' => 'hiddenleaf-notice-board',
                'name' => 'Notice Board',
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
            'module_name' => 'notice-board',
        ]);

        $this->noticeService = app(NoticeService::class);
    }

    public function test_workspace_isolation_and_scoping(): void
    {
        $otherWorkspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $notice1 = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Workspace 1 Notice',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'priority' => 'normal',
            'target_type' => 'all',
        ], $this->user);

        $notice2 = $this->noticeService->createNotice($otherWorkspace, [
            'title' => 'Workspace 2 Notice',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'priority' => 'normal',
            'target_type' => 'all',
        ], $this->user);

        $ws1Notices = Notice::forWorkspace($this->workspace->organization_id, $this->workspace->id)->get();
        $this->assertTrue($ws1Notices->contains('id', $notice1->id));
        $this->assertFalse($ws1Notices->contains('id', $notice2->id));
    }

    public function test_notice_creation_and_targeting(): void
    {
        // Targeted specifically to memberUser
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Targeted Confidential Notice',
            'description' => 'Important details',
            'start_date' => Carbon::today()->toDateString(),
            'priority' => 'urgent',
            'status' => 'published',
            'target_type' => 'specific_users',
            'target_ids' => [$this->memberUser->id],
            'require_acknowledgment' => true,
        ], $this->user);

        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'title' => 'Targeted Confidential Notice',
            'target_type' => 'specific_users',
        ]);

        $this->assertDatabaseHas('notice_targets', [
            'notice_id' => $notice->id,
            'user_id' => $this->memberUser->id,
        ]);

        // Visible to memberUser
        $visibleToMember = Notice::query()->visibleTo($this->memberUser, $this->workspace)->get();
        $this->assertTrue($visibleToMember->contains('id', $notice->id));

        // Another user who is not targeted
        $otherUser = User::factory()->create();
        $this->workspace->members()->attach($otherUser);
        $visibleToOther = Notice::query()->visibleTo($otherUser, $this->workspace)->get();
        $this->assertFalse($visibleToOther->contains('id', $notice->id));
    }

    public function test_notice_publish_and_deactivate_lifecycle(): void
    {
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Draft Notice',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'priority' => 'normal',
            'target_type' => 'all',
        ], $this->user);

        // Draft should not be visible to general members
        $visibleNotices = Notice::query()->visibleTo($this->memberUser, $this->workspace)->get();
        $this->assertFalse($visibleNotices->contains('id', $notice->id));

        // Publish
        $this->noticeService->publish($notice, $this->user);
        $this->assertEquals('published', $notice->fresh()->status);

        $visibleNotices = Notice::query()->visibleTo($this->memberUser, $this->workspace)->get();
        $this->assertTrue($visibleNotices->contains('id', $notice->id));

        // Deactivate
        $this->noticeService->deactivate($notice, $this->user);
        $this->assertEquals('deactivated', $notice->fresh()->status);

        $visibleNotices = Notice::query()->visibleTo($this->memberUser, $this->workspace)->get();
        $this->assertFalse($visibleNotices->contains('id', $notice->id));
    }

    public function test_cannot_publish_notice_with_future_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Future Notice',
            'start_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'draft',
            'priority' => 'normal',
            'target_type' => 'all',
        ], $this->user);

        $this->noticeService->publish($notice, $this->user);
    }

    public function test_toggle_pin(): void
    {
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Important Pin Notice',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'is_pinned' => false,
        ], $this->user);

        $this->assertFalse($notice->is_pinned);

        $this->noticeService->togglePin($notice, $this->user);
        $this->assertTrue($notice->fresh()->is_pinned);

        $this->noticeService->togglePin($notice, $this->user);
        $this->assertFalse($notice->fresh()->is_pinned);
    }

    public function test_read_and_acknowledgment_flow(): void
    {
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Security Protocol 2026',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'require_acknowledgment' => true,
            'target_type' => 'all',
        ], $this->user);

        $this->assertFalse($notice->isReadBy($this->memberUser));
        $this->assertFalse($notice->isAcknowledgedBy($this->memberUser));

        // Mark read
        $this->noticeService->markRead($notice, $this->memberUser);
        $this->assertTrue($notice->fresh()->isReadBy($this->memberUser));
        $this->assertFalse($notice->fresh()->isAcknowledgedBy($this->memberUser));

        // Acknowledge
        $this->noticeService->acknowledge($notice, $this->memberUser);
        $this->assertTrue($notice->fresh()->isAcknowledgedBy($this->memberUser));
    }

    public function test_notice_comments_and_threaded_replies(): void
    {
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Annual Town Hall Discussion',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'allow_comments' => true,
            'target_type' => 'all',
        ], $this->user);

        // Add parent comment
        $comment = $this->noticeService->addComment($notice, $this->memberUser, 'Will there be remote attendance?');
        $this->assertDatabaseHas('notice_comments', [
            'id' => $comment->id,
            'notice_id' => $notice->id,
            'comment' => 'Will there be remote attendance?',
            'parent_id' => null,
        ]);

        // Add reply to comment
        $reply = $this->noticeService->addComment($notice, $this->user, 'Yes, via video conference link.', $comment->id);
        $this->assertDatabaseHas('notice_comments', [
            'id' => $reply->id,
            'parent_id' => $comment->id,
            'comment' => 'Yes, via video conference link.',
        ]);

        $this->assertEquals(1, $comment->replies()->count());

        // Delete reply
        $this->noticeService->deleteComment($reply, $this->user);
        $this->assertDatabaseMissing('notice_comments', ['id' => $reply->id]);
    }

    public function test_http_board_and_show_endpoints(): void
    {
        $notice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Q3 All Hands Meeting',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'require_acknowledgment' => true,
            'allow_comments' => true,
            'target_type' => 'all',
        ], $this->user);

        // Access board
        $response = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get('/notice-board/board');

        $response->assertStatus(200);

        // Access show (auto-marks read)
        $showResponse = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get("/notice-board/notices/{$notice->id}");

        $showResponse->assertStatus(200);
        $this->assertTrue($notice->fresh()->isReadBy($this->memberUser));

        // Acknowledge via HTTP POST
        $ackResponse = $this->actingAs($this->memberUser)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->postJson("/notice-board/notices/{$notice->id}/acknowledge");

        $ackResponse->assertStatus(200);
        $ackResponse->assertJson(['success' => true]);
        $this->assertTrue($notice->fresh()->isAcknowledgedBy($this->memberUser));
    }

    public function test_mrfox_notice_board_summary_tool(): void
    {
        // Create 1 critical notice, 1 normal notice
        $criticalNotice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Server Maintenance',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'priority' => 'critical',
            'require_acknowledgment' => true,
            'target_type' => 'all',
        ], $this->user);

        $normalNotice = $this->noticeService->createNotice($this->workspace, [
            'title' => 'Lunch Catering Menu',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'published',
            'priority' => 'normal',
            'require_acknowledgment' => false,
            'target_type' => 'all',
        ], $this->user);

        $tool = new NoticeBoardSummaryTool();
        $this->assertEquals('notice_board.summary', $tool->name());
        $this->assertEquals('notice-board.view', $tool->requiredPermission());
        $this->assertEquals('notice-board', $tool->requiredModule());

        $context = new ToolContext(
            user: $this->memberUser,
            organization: $this->organization,
            workspace: $this->workspace
        );

        $result = $tool->execute($context, []);
        $this->assertTrue($result->success);

        $data = $result->data;
        $this->assertEquals(2, $data['active_notices_count']);
        $this->assertEquals(1, $data['critical_notices_count']);
        $this->assertEquals(2, $data['unread_for_user']);
        $this->assertEquals(1, $data['pending_acknowledgments']);

        // Once member acknowledges critical notice
        $this->noticeService->markRead($criticalNotice, $this->memberUser);
        $this->noticeService->acknowledge($criticalNotice, $this->memberUser);

        $result2 = $tool->execute($context, []);
        $this->assertTrue($result2->success);
        $data2 = $result2->data;
        $this->assertEquals(1, $data2['unread_for_user']);
        $this->assertEquals(0, $data2['pending_acknowledgments']);
    }
}
