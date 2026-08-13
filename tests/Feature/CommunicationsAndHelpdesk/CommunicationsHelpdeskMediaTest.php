<?php

namespace Tests\Feature\CommunicationsAndHelpdesk;

use App\Models\ChMessage;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use App\Models\Media;
use App\Models\MediaDirectory;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationsHelpdeskMediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $this->user1 = User::factory()->create(['name' => 'Alice Admin']);
        $this->user2 = User::factory()->create(['name' => 'Bob Member']);

        $this->org = Organization::factory()->create(['owner_id' => $this->user1->id]);
        $this->ws = Workspace::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user1->id,
        ]);

        $this->user1->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->user1->workspaces()->attach($this->ws->id);

        $this->user2->organizations()->attach($this->org->id, ['role' => 'member']);
        $this->user2->workspaces()->attach($this->ws->id);
    }

    public function test_helpdesk_lifecycle(): void
    {
        // 1. Create Category
        $catResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/helpdesk-categories', [
                'name' => 'Technical Support',
                'color' => '#ef4444',
            ]);
        $catResp->assertRedirect('/helpdesk-categories');
        $cat = HelpdeskCategory::first();
        $this->assertEquals('Technical Support', $cat->name);

        // 2. Create Ticket
        $ticketResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/helpdesk-tickets', [
                'subject' => 'Cannot connect to database',
                'category_id' => $cat->id,
                'priority' => 'high',
                'description' => 'Getting timeout error on port 5432',
            ]);
        $ticketResp->assertRedirect('/helpdesk-tickets');
        $ticket = HelpdeskTicket::first();
        $this->assertEquals('open', $ticket->status);
        $this->assertEquals('high', $ticket->priority);

        // 3. Post Reply
        $replyResp = $this->actingAs($this->user2)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post("/helpdesk-tickets/{$ticket->id}/replies", [
                'description' => 'Checking firewall rules right now.',
            ]);
        $replyResp->assertSessionHas('success');
        $this->assertDatabaseHas('helpdesk_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->user2->id,
            'description' => 'Checking firewall rules right now.',
        ]);
    }

    public function test_media_library_directories_and_uploads(): void
    {
        // 1. Create Directory
        $dirResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->postJson('/media/directories', [
                'name' => 'Invoices 2026',
            ]);
        $dirResp->assertStatus(200);
        $dir = MediaDirectory::first();
        $this->assertEquals('Invoices 2026', $dir->name);

        // 2. Upload file
        $file = UploadedFile::fake()->create('invoice_jan.pdf', 500, 'application/pdf');
        $uploadResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/media/batch-store', [
                'files' => [$file],
                'directory_id' => $dir->id,
            ]);
        $uploadResp->assertStatus(200);
        $media = Media::first();
        $this->assertEquals('invoice_jan', $media->name);
        $this->assertEquals($dir->id, $media->directory_id);
    }

    public function test_messenger_chat_and_favorites(): void
    {
        // 1. Send Message
        $sendResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->postJson('/chats/send', [
                'id' => $this->user2->id,
                'message' => 'Hey Bob, did you review the proposal?',
            ]);
        $sendResp->assertStatus(200);
        $this->assertDatabaseHas('ch_messages', [
            'from_id' => $this->user1->id,
            'to_id' => $this->user2->id,
            'body' => 'Hey Bob, did you review the proposal?',
            'seen' => false,
        ]);

        // 2. Retrieve messages as recipient (marks seen)
        $getResp = $this->actingAs($this->user2)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->getJson("/chats/get-messages?id={$this->user1->id}");
        $getResp->assertStatus(200);
        $this->assertDatabaseHas('ch_messages', [
            'from_id' => $this->user1->id,
            'to_id' => $this->user2->id,
            'seen' => true,
        ]);

        // 3. Toggle favorite
        $favResp = $this->actingAs($this->user1)
            ->postJson('/chats/toggle-favorite', [
                'user_id' => $this->user2->id,
            ]);
        $favResp->assertStatus(200);
        $favResp->assertJson(['favorite' => true]);
    }

    public function test_ai_assistant_session_and_chat(): void
    {
        // 1. Create AI Chat Session
        $sessionResp = $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->postJson('/ai-agent/chat/session', [
                'title' => 'Q3 Revenue Forecasting',
            ]);
        $sessionResp->assertStatus(200);
        $session = $sessionResp->json('session');

        // 2. Chat with assistant
        $chatResp = $this->actingAs($this->user1)
            ->postJson('/ai-agent/chat', [
                'session_id' => $session['id'],
                'message' => 'Analyze our quarterly trends',
            ]);
        $chatResp->assertStatus(200);
        $this->assertDatabaseHas('ai_agent_chat_messages', [
            'session_id' => $session['id'],
            'role' => 'user',
            'message' => 'Analyze quarterly trends' ? 'Analyze our quarterly trends' : '',
        ]);
        $this->assertDatabaseHas('ai_agent_chat_messages', [
            'session_id' => $session['id'],
            'role' => 'assistant',
        ]);

        $this->actingAs($this->user2)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->getJson("/ai-agent/chat/messages/{$session['id']}")
            ->assertNotFound();

        $this->actingAs($this->user1)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->patchJson("/ai-agent/chat/session/{$session['id']}/archive")
            ->assertOk();
        $this->assertDatabaseMissing('ai_agent_chat_sessions', [
            'id' => $session['id'],
            'archived_at' => null,
        ]);
    }

    public function test_helpdesk_media_and_messenger_reject_cross_workspace_idor(): void
    {
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create([
            'organization_id' => $foreignOrg->id,
            'created_by' => $foreignOwner->id,
        ]);
        $foreignOwner->organizations()->attach($foreignOrg->id, ['role' => 'owner']);
        $foreignOwner->workspaces()->attach($foreignWorkspace->id);

        $ticket = HelpdeskTicket::create([
            'ticket_id' => 'HD-FOREIGN',
            'name' => $foreignOwner->name,
            'email' => $foreignOwner->email,
            'subject' => 'Foreign ticket',
            'status' => 'open',
            'priority' => 'high',
            'description' => 'Private tenant issue',
            'organization_id' => $foreignOrg->id,
            'workspace_id' => $foreignWorkspace->id,
            'created_by' => $foreignOwner->id,
        ]);
        $media = Media::create([
            'name' => 'private',
            'file_name' => 'private.txt',
            'mime_type' => 'text/plain',
            'disk' => 'local',
            'size' => 7,
            'path' => 'workspaces/'.$foreignWorkspace->id.'/media/private.txt',
            'workspace_id' => $foreignWorkspace->id,
            'created_by' => $foreignOwner->id,
        ]);
        ChMessage::create([
            'from_id' => $foreignOwner->id,
            'to_id' => $this->user1->id,
            'body' => 'Foreign workspace secret',
            'workspace_id' => $foreignWorkspace->id,
        ]);

        $context = ['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id];
        $this->actingAs($this->user1)->withSession($context)
            ->get("/helpdesk-tickets/{$ticket->id}")->assertNotFound();
        $this->actingAs($this->user1)->withSession($context)
            ->get("/media/{$media->id}/preview")->assertNotFound();
        $this->actingAs($this->user1)->withSession($context)
            ->getJson("/chats/get-messages?id={$foreignOwner->id}")->assertNotFound();
        $this->actingAs($this->user1)->withSession($context)
            ->getJson('/chats/check-new-messages')->assertJsonPath('new_messages', 0);
    }

    public function test_presence_and_private_media_download_are_functional(): void
    {
        $context = ['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id];
        Storage::disk('local')->put("workspaces/{$this->ws->id}/media/private.txt", 'private');
        $media = Media::create([
            'name' => 'private',
            'file_name' => 'private.txt',
            'mime_type' => 'text/plain',
            'disk' => 'local',
            'size' => 7,
            'path' => "workspaces/{$this->ws->id}/media/private.txt",
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user1->id,
        ]);

        $this->actingAs($this->user2)->withSession($context)
            ->postJson('/chats/update-presence')->assertJsonPath('online', true);
        $this->actingAs($this->user1)->withSession($context)
            ->getJson('/chats/online-users')->assertJsonFragment(['id' => $this->user2->id]);
        $this->actingAs($this->user1)->withSession($context)
            ->get("/media/{$media->id}/download")->assertOk();
    }
}
