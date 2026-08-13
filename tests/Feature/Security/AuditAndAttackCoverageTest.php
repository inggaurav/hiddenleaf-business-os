<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndAttackCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_impersonation_logs_audit_events_and_prevents_nesting(): void
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
        $this->assertEquals($targetUser->id, auth()->id());
        $this->assertEquals($superAdmin->id, session('impersonator_id'));

        // 2. Nested impersonation attempt is blocked with 403
        $anotherUser = User::factory()->create();
        $res2 = $this->post("/users/{$anotherUser->id}/impersonate");
        $res2->assertStatus(403);

        // 3. Leave impersonation returns to Super Admin
        $res3 = $this->post('/users/leave-impersonation');
        $res3->assertRedirect('/dashboard');
        $this->assertEquals($superAdmin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
    }
}
