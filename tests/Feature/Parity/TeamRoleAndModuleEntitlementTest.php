<?php

namespace Tests\Feature\Parity;

use App\Domain\Automation\Triggers\TriggerRegistry;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamRoleAndModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_and_taskly_team_role_can_crawl_every_child_but_not_other_modules_or_admin(): void
    {
        [$user, $organization, $workspace] = $this->tenant(['lead', 'taskly'], ['crm.view', 'crm.manage', 'taskly.view', 'taskly.manage']);
        $request = $this->actingAs($user)->withSession($this->tenantSession($organization, $workspace));

        foreach (['/crm/dashboard', '/crm/leads', '/crm/deals', '/crm/pipelines', '/crm/web-forms', '/crm/activities', '/crm/notes'] as $url) {
            $request->get($url)->assertOk();
        }
        foreach (['/taskly/dashboard', '/taskly/projects', '/taskly/tasks', '/taskly/milestones', '/taskly/timesheets', '/taskly/issues'] as $url) {
            $request->get($url)->assertOk();
        }

        foreach (['/accounting', '/hrm', '/pos', '/plans', '/modules', '/settings', '/super-admin/dashboard'] as $url) {
            $this->assertSame(403, $request->get($url)->status(), $url);
        }
    }

    public function test_crm_view_only_role_has_no_manage_controls_and_every_direct_mutation_is_forbidden(): void
    {
        [$user, $organization, $workspace] = $this->tenant(['lead'], ['crm.view']);
        $request = $this->actingAs($user)->withSession($this->tenantSession($organization, $workspace));

        foreach (['/crm/dashboard', '/crm/leads', '/crm/deals', '/crm/pipelines', '/crm/web-forms', '/crm/activities', '/crm/notes'] as $url) {
            $response = $request->get($url)->assertOk();
            if ($url !== '/crm/dashboard') {
                $response->assertInertia(fn (Assert $page) => $page->where('canManage', false));
            }
        }

        foreach ([
            ['/crm/leads', []], ['/crm/pipelines', []], ['/crm/leads/999/convert', []],
            ['/crm/lead/999/activities', []], ['/crm/lead/999/notes', []], ['/crm/webforms', []],
            ['/crm/deals/999/move', []],
        ] as [$url, $payload]) {
            $this->assertSame(403, $request->post($url, $payload)->status(), $url);
        }
    }

    public function test_disabling_hrm_removes_ui_settings_api_tools_and_automation_then_reenable_restores_them(): void
    {
        [$user, $organization, $workspace] = $this->tenant(['hrm'], ['hrm.view', 'hrm.manage', 'settings.view', 'settings.modules.manage']);
        $session = $this->tenantSession($organization, $workspace);
        $context = app(BusinessContextService::class)->createToolContext($user, $workspace);

        $this->assertArrayHasKey('hr.employee.summary', app(MrFoxToolRegistry::class)->availableFor($context));
        $this->assertArrayHasKey('hrm.leave.requested', app(TriggerRegistry::class)->availableFor($workspace));
        $this->actingAs($user)->withSession($session)->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('settingsSections.0.id', 'module.hrm')
            ->where('tenant.modules.0', 'hrm'));

        UserActiveModule::where('workspace_id', $workspace->id)->where('module_name', 'hrm')->delete();

        $this->actingAs($user)->withSession($session)->get('/hrm')->assertForbidden();
        $this->actingAs($user)->withSession($session)->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('settingsSections', [])
            ->where('tenant.modules', ['core']));
        $this->actingAs($user)->withSession($session)->post('/settings', ['_section' => 'module.hrm', 'values' => ['hrm.work_week' => 'Monday-Friday']])->assertForbidden();

        Sanctum::actingAs($user);
        $this->withHeader('X-Workspace-ID', (string) $workspace->id)->getJson('/api/v1/hrm/employees')->assertForbidden();
        $this->assertArrayNotHasKey('hr.employee.summary', app(MrFoxToolRegistry::class)->availableFor($context));
        $this->assertArrayNotHasKey('hrm.leave.requested', app(TriggerRegistry::class)->availableFor($workspace));

        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'hrm']);
        $this->actingAs($user)->withSession($session)->get('/hrm')->assertOk();
        $this->assertArrayHasKey('hr.employee.summary', app(MrFoxToolRegistry::class)->availableFor($context));
        $this->assertArrayHasKey('hrm.leave.requested', app(TriggerRegistry::class)->availableFor($workspace));
    }

    /** @return array{User, Organization, Workspace} */
    private function tenant(array $modules, array $permissions): array
    {
        $this->seed();
        $owner = User::factory()->create(['role' => 'company_admin']);
        $user = User::factory()->create(['role' => 'user']);
        $plan = Plan::create(['name' => 'Selected modules', 'modules' => $modules, 'status' => true, 'created_by' => $owner->id]);
        $organization = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
        $organization->members()->attach($user, ['role' => 'member']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'selected-role', 'display_name' => 'Selected Role', 'is_system' => false]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        $workspace->members()->attach($user, ['role_id' => $role->id]);
        foreach ($modules as $module) {
            UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => $module]);
        }

        return [$user, $organization, $workspace];
    }

    private function tenantSession(Organization $organization, Workspace $workspace): array
    {
        return ['active_organization_id' => $organization->id, 'active_workspace_id' => $workspace->id];
    }
}
