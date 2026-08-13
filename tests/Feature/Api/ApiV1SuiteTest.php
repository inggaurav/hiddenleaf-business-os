<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\Subscription;
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
        Storage::fake('local');

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

    public function test_tenant_endpoints_require_an_owned_workspace_context(): void
    {
        $token = $this->user->createToken('tenant-test')->plainTextToken;
        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create([
            'organization_id' => $foreignOrg->id,
            'created_by' => $foreignOwner->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/products-services')->assertUnprocessable();
        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $foreignWorkspace->id)
            ->getJson('/api/v1/products-services')
            ->assertNotFound();
    }

    public function test_products_and_services_are_paginated_and_tenant_scoped(): void
    {
        ProductServiceItem::create([
            'name' => 'Visible Product',
            'sku' => 'VISIBLE-1',
            'type' => 'product',
            'organization_id' => $this->org->id,
            'workspace_id' => $this->ws->id,
            'created_by' => $this->user->id,
        ]);

        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create([
            'organization_id' => $foreignOrg->id,
            'created_by' => $foreignOwner->id,
        ]);
        ProductServiceItem::create([
            'name' => 'Foreign Product',
            'sku' => 'FOREIGN-1',
            'type' => 'product',
            'organization_id' => $foreignOrg->id,
            'workspace_id' => $foreignWorkspace->id,
            'created_by' => $foreignOwner->id,
        ]);

        $token = $this->user->createToken('catalog-test')->plainTextToken;
        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $this->ws->id)
            ->getJson('/api/v1/products-services?type=product&per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Visible Product')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonMissing(['name' => 'Foreign Product']);
    }

    public function test_profile_password_and_token_lifecycle(): void
    {
        $token = $this->user->createToken('current')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/profile', [
            'name' => 'Updated API User',
            'locale' => 'fr',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated API User')
            ->assertJsonPath('data.locale', 'fr');

        $created = $this->withToken($token)->postJson('/api/v1/tokens', [
            'name' => 'integration',
            'abilities' => ['read'],
        ])->assertCreated();

        $tokenId = $created->json('token_id');
        $this->withToken($token)->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonFragment(['id' => $tokenId, 'name' => 'integration']);

        $this->withToken($token)->deleteJson("/api/v1/tokens/{$tokenId}")->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->withToken($token)->putJson('/api/v1/password', [
            'current_password' => 'password123',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('SecurePassword123', $this->user->fresh()->password));
    }

    public function test_role_directories_and_subscription_are_workspace_scoped(): void
    {
        $client = User::factory()->create(['role' => 'client', 'name' => 'Tenant Client']);
        $client->organizations()->attach($this->org->id, ['role' => 'client']);
        $client->workspaces()->attach($this->ws->id);
        $foreignClient = User::factory()->create(['role' => 'client', 'name' => 'Foreign Client']);
        $plan = Plan::create([
            'name' => 'API Subscription',
            'package_price_monthly' => 10,
            'package_price_yearly' => 100,
            'number_of_users' => 5,
            'workspace_limit' => 2,
            'storage_limit' => 512,
            'status' => true,
        ]);
        Subscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
        $token = $this->user->createToken('directory-test')->plainTextToken;

        $this->withToken($token)->withHeader('X-Workspace-ID', $this->ws->id)
            ->getJson('/api/v1/client-users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tenant Client'])
            ->assertJsonMissing(['name' => $foreignClient->name]);

        $this->withToken($token)->withHeader('X-Workspace-ID', $this->ws->id)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.name', 'API Subscription')
            ->assertJsonPath('data.is_active', true);
    }
}
