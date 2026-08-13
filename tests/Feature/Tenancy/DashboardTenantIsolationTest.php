<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_tenant_dashboard_only_returns_scoped_organization_and_workspace_metrics(): void
    {
        // Org A with 1 user and 1 workspace
        $userA = User::factory()->create(['name' => 'User A']);
        $orgA = Organization::factory()->create(['name' => 'Org A', 'owner_id' => $userA->id]);
        $wsA = Workspace::factory()->create(['name' => 'Workspace A', 'organization_id' => $orgA->id, 'created_by' => $userA->id]);
        $userA->organizations()->attach($orgA->id, ['role' => 'owner']);
        $userA->workspaces()->attach($wsA->id);

        // Org B with 5 users and 3 workspaces
        $userB = User::factory()->create(['name' => 'User B']);
        $orgB = Organization::factory()->create(['name' => 'Org B', 'owner_id' => $userB->id]);
        $wsB1 = Workspace::factory()->create(['organization_id' => $orgB->id]);
        $wsB2 = Workspace::factory()->create(['organization_id' => $orgB->id]);
        $wsB3 = Workspace::factory()->create(['organization_id' => $orgB->id]);
        $orgB->members()->attach(User::factory()->count(4)->create()->pluck('id'), ['role' => 'member']);
        $userB->organizations()->attach($orgB->id, ['role' => 'owner']);
        $userB->workspaces()->attach($wsB1->id);

        // Foreign audit log in Org B
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'actor_id' => $userB->id,
            'organization_id' => $orgB->id,
            'workspace_id' => $wsB1->id,
            'action' => 'secret.org_b_action',
            'entity_type' => 'secret',
            'created_at' => now(),
        ]);

        // User A visits dashboard
        $response = $this->actingAs($userA)
            ->withSession([
                'active_organization_id' => $orgA->id,
                'active_workspace_id' => $wsA->id,
            ])
            ->get('/dashboard');

        $response->assertStatus(200);
        $pageProps = $response->getOriginalContent()->getData()['page']['props'];

        // Assert stats are strictly scoped to Org A (1 user, 1 workspace), NOT total across all orgs (6 users, 4 workspaces)
        $this->assertEquals(1, $pageProps['stats']['users']);
        $this->assertEquals(1, $pageProps['stats']['workspaces']);

        // Assert recent logs do not leak Org B logs
        foreach ($pageProps['recentLogs'] as $log) {
            $this->assertNotEquals('secret.org_b_action', $log->action);
        }
    }
}
