<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiV1SuiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $this->user = User::factory()->create([
            'email' => 'apiuser@hiddenleaf.test',
            'password' => Hash::make('password123'),
        ]);

        $this->org = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->ws = Workspace::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
        ]);

        $this->user->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->user->workspaces()->attach($this->ws->id);
    }

    public function test_api_login_returns_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'apiuser@hiddenleaf.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'token',
            'user' => ['id', 'name', 'email', 'role'],
        ]);
    }

    public function test_api_authentication_and_user_profile(): void
    {
        // Unauthenticated is rejected
        $unauthResp = $this->getJson('/api/v1/user');
        $unauthResp->assertStatus(401);

        // Authenticated succeeds
        $token = $this->user->createToken('test-token')->plainTextToken;

        $authResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $authResp->assertStatus(200);
        $authResp->assertJsonPath('user.email', 'apiuser@hiddenleaf.test');
    }

    public function test_api_public_plans_listing(): void
    {
        Plan::create([
            'name' => 'API Plan 1',
            'package_price_monthly' => 15.00,
            'package_price_yearly' => 150.00,
            'number_of_users' => 5,
            'storage_limit' => 1024,
            'status' => true,
        ]);

        $response = $this->getJson('/api/v1/plans');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'API Plan 1']);
    }

    public function test_api_helpdesk_ticket_creation_and_listing(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $createResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-ID', $this->ws->id)
            ->withHeader('X-Organization-ID', $this->org->id)
            ->postJson('/api/v1/helpdesk/tickets', [
                'subject' => 'API Helpdesk Issue',
                'priority' => 'medium',
                'description' => 'Created via REST API v1 endpoint',
            ]);

        $createResp->assertStatus(201);
        $this->assertDatabaseHas('helpdesk_tickets', [
            'subject' => 'API Helpdesk Issue',
            'created_by' => $this->user->id,
        ]);

        $listResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-ID', $this->ws->id)
            ->getJson('/api/v1/helpdesk/tickets');

        $listResp->assertStatus(200);
        $listResp->assertJsonFragment(['subject' => 'API Helpdesk Issue']);
    }

    public function test_api_media_upload_and_logout(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;
        $file = UploadedFile::fake()->image('api_upload.png', 100, 100);

        // Upload media
        $uploadResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-ID', $this->ws->id)
            ->post('/api/v1/media/upload', ['file' => $file]);

        $uploadResp->assertStatus(201);
        $this->assertDatabaseHas('media', [
            'name' => 'api_upload',
            'created_by' => $this->user->id,
        ]);

        // Logout
        $logoutResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $logoutResp->assertStatus(200);
        $this->assertEquals(0, $this->user->tokens()->count());
    }
}
