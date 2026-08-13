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
        $superAdmin = User::factory()->create(['name' => 'Super Admin', 'role' => 'super_admin']);
        $org = Organization::factory()->create(['owner_id' => $superAdmin->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $superAdmin->id]);

        $targetUser = User::factory()->create(['name' => 'Target User', 'role' => 'member']);
        $org->members()->attach($targetUser->id, ['role' => 'member']);
        $ws->members()->attach($targetUser->id);

        // 1. Super Admin impersonates Target User
        $res1 = $this->actingAs($superAdmin)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post("/users/{$targetUser->id}/impersonate");

        $res1->assertRedirect('/dashboard');

        // Assert database audit record for impersonation start
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.start',
            'actor_id' => $superAdmin->id,
            'entity_type' => 'user',
            'entity_id' => (string) $targetUser->id,
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
}
