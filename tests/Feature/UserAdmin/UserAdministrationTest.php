<?php

namespace Tests\Feature\UserAdmin;

use App\Models\LoginDetail;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $companyAdmin;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'role' => 'super_admin',
            'email' => 'admin@hiddenleaf.test',
        ]);

        $this->companyAdmin = User::factory()->create([
            'name' => 'Company Admin',
            'role' => 'company_admin',
            'email' => 'company@hiddenleaf.test',
        ]);

        $this->org = Organization::factory()->create(['owner_id' => $this->companyAdmin->id]);
        $this->ws = Workspace::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->companyAdmin->id,
        ]);

        $this->companyAdmin->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->companyAdmin->workspaces()->attach($this->ws->id);
    }

    public function test_user_creation_and_plan_assignment(): void
    {
        $plan = Plan::create([
            'name' => 'Starter Tier',
            'package_price_monthly' => 20,
            'package_price_yearly' => 200,
            'number_of_users' => 5,
            'storage_limit' => 1024 * 1024 * 1024,
            'status' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->post('/users', [
            'name' => 'New Tenant Owner',
            'email' => 'newowner@test.com',
            'password' => 'secret12345',
            'role' => 'company_admin',
            'plan_id' => $plan->id,
        ]);

        $response->assertRedirect('/users');
        $user = User::where('email', 'newowner@test.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($plan->id, $user->active_plan);

        // Super admin manually changes plan
        $plan2 = Plan::create([
            'name' => 'Scale Tier',
            'package_price_monthly' => 100,
            'package_price_yearly' => 1000,
            'number_of_users' => 50,
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'status' => true,
        ]);

        $assignResp = $this->actingAs($this->superAdmin)->post("/users/{$user->id}/assign-plan", [
            'plan_id' => $plan2->id,
            'duration' => 'Year',
        ]);

        $assignResp->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals($plan2->id, $user->active_plan);
    }

    public function test_password_change_and_status_toggle(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $employee->organizations()->attach($this->org->id, ['role' => 'member']);
        $employee->workspaces()->attach($this->ws->id);

        // 1. Change password
        $passResp = $this->actingAs($this->superAdmin)->post("/users/{$employee->id}/change-password", [
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ]);
        $passResp->assertSessionHas('success');
        $employee->refresh();
        $this->assertTrue(Hash::check('brandnewpassword123', $employee->password));

        // 2. Toggle status
        $this->assertTrue($employee->is_active);
        $toggleResp = $this->actingAs($this->superAdmin)->patch("/users/{$employee->id}/toggle-status");
        $toggleResp->assertSessionHas('success');
        $employee->refresh();
        $this->assertFalse($employee->is_active);
    }

    public function test_impersonation_and_leave(): void
    {
        $targetUser = User::factory()->create(['name' => 'Impersonated User']);

        // Start impersonation
        $impResp = $this->actingAs($this->superAdmin)->post('/users/{targetUser->id}' ? "/users/{$targetUser->id}/impersonate" : '');
        $impResp->assertRedirect('/dashboard');
        $this->assertEquals($targetUser->id, auth()->id());
        $this->assertEquals($this->superAdmin->id, session('impersonator_id'));

        // Leave impersonation
        $leaveResp = $this->post('/users/leave-impersonation');
        $leaveResp->assertRedirect('/dashboard');
        $this->assertEquals($this->superAdmin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_login_history_records(): void
    {
        LoginDetail::create([
            'user_id' => $this->companyAdmin->id,
            'ip' => '192.168.1.100',
            'date' => now(),
            'details' => json_encode(['browser' => 'Chrome', 'os' => 'Windows']),
        ]);

        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->get('/users-login-history');

        $response->assertStatus(200);
    }
}
