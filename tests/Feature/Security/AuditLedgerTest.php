<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_impersonation_start_and_end_persist_audit_log_database_records(): void
    {
        $requestId = '550e8400-e29b-41d4-a716-446655440000';
        $superAdmin = User::factory()->create(['name' => 'Super Admin', 'role' => 'super_admin']);
        $org = Organization::factory()->create(['owner_id' => $superAdmin->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $superAdmin->id]);

        $targetUser = User::factory()->create(['name' => 'Target User', 'role' => 'member']);
        $org->members()->attach($targetUser->id, ['role' => 'member']);
        $ws->members()->attach($targetUser->id);

        // 1. Super Admin impersonates Target User
        $res1 = $this->actingAs($superAdmin)
            ->withHeader('X-Request-ID', $requestId)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post("/users/{$targetUser->id}/impersonate");

        $res1->assertRedirect('/dashboard');
        $res1->assertHeader('X-Request-ID', $requestId);

        // Assert database audit record for impersonation start
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.start',
            'actor_id' => $superAdmin->id,
            'entity_type' => 'user',
            'entity_id' => (string) $targetUser->id,
            'request_id' => $requestId,
        ]);

        // 2. Leave impersonation
        $res2 = $this->post('/users/leave-impersonation');
        $res2->assertRedirect('/dashboard');

        // Assert database audit record for impersonation end
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.end',
            'actor_id' => $superAdmin->id,
            'entity_type' => 'user',
            'entity_id' => (string) $targetUser->id,
        ]);
    }

    public function test_web_and_api_responses_receive_valid_request_ids(): void
    {
        $web = $this->withHeader('X-Request-ID', 'not-a-uuid')->get('/login');
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $web->headers->get('X-Request-ID')));

        $apiRequestId = 'c56a4180-65aa-42ec-a945-5fd21dec0538';
        $this->withHeader('X-Request-ID', $apiRequestId)
            ->getJson('/api/v1/plans')
            ->assertOk()
            ->assertHeader('X-Request-ID', $apiRequestId);
    }
}
