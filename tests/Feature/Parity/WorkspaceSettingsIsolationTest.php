<?php

namespace Tests\Feature\Parity;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\HierarchicalSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceSettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_workspaces_immediately_replaces_settings_modules_permissions_and_mrfox_context(): void
    {
        $this->seed();
        $owner = User::factory()->create(['role' => 'company_admin']);
        $member = User::factory()->create(['role' => 'user']);
        $plan = Plan::create(['name' => 'CRM and HRM', 'modules' => ['lead', 'hrm'], 'status' => true, 'created_by' => $owner->id]);
        $organization = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $workspaceA = Workspace::factory()->create(['organization_id' => $organization->id, 'name' => 'Sales India', 'created_by' => $owner->id]);
        $workspaceB = Workspace::factory()->create(['organization_id' => $organization->id, 'name' => 'People US', 'created_by' => $owner->id]);
        $organization->members()->attach($member, ['role' => 'member']);
        $roleA = $this->role($organization, 'sales-settings', ['workspace.switch', 'settings.view', 'settings.localization.manage', 'settings.modules.manage', 'crm.view']);
        $roleB = $this->role($organization, 'people-settings', ['workspace.switch', 'settings.view', 'settings.localization.manage', 'settings.modules.manage', 'hrm.view']);
        $workspaceA->members()->attach($member, ['role_id' => $roleA->id]);
        $workspaceB->members()->attach($member, ['role_id' => $roleB->id]);
        UserActiveModule::create(['workspace_id' => $workspaceA->id, 'module_name' => 'lead']);
        UserActiveModule::create(['workspace_id' => $workspaceB->id, 'module_name' => 'hrm']);

        $settings = app(HierarchicalSettingService::class);
        foreach ([['site_currency', 'INR'], ['site_currency_symbol', '₹'], ['timezone', 'Asia/Kolkata'], ['crm.default_pipeline', 'India Sales']] as [$key, $value]) {
            $settings->put($owner, 'workspace', $workspaceA->id, $key, $value, $organization, $workspaceA);
        }
        foreach ([['site_currency', 'USD'], ['site_currency_symbol', '$'], ['timezone', 'America/New_York'], ['hrm.work_week', 'Monday-Friday']] as [$key, $value]) {
            $settings->put($owner, 'workspace', $workspaceB->id, $key, $value, $organization, $workspaceB);
        }

        $session = ['active_organization_id' => $organization->id, 'active_workspace_id' => $workspaceA->id];
        $this->actingAs($member)->withSession($session)->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('resolvedSettings.site_currency', 'INR')
            ->where('resolvedSettings.timezone', 'Asia/Kolkata')
            ->where('resolvedSettings', fn ($values) => ($values['crm.default_pipeline'] ?? null) === 'India Sales')
            ->where('settingsSections.1.id', 'module.crm')
            ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('crm.view') && ! collect($permissions)->contains('hrm.view')));

        $contextA = app(BusinessContextService::class)->build($member, $workspaceA);
        $this->assertSame('INR', $contextA['currency']['code']);
        $this->assertContains('lead', $contextA['enabled_modules']);
        $this->assertNotContains('hrm', $contextA['enabled_modules']);

        $this->actingAs($member)->withSession($session)->post('/workspaces/switch', ['workspace_id' => $workspaceB->id])->assertRedirect();
        $this->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('resolvedSettings.site_currency', 'USD')
            ->where('resolvedSettings.timezone', 'America/New_York')
            ->where('resolvedSettings', fn ($values) => $values->get('hrm.work_week') === 'Monday-Friday' && ! $values->has('crm.default_pipeline'))
            ->where('settingsSections.1.id', 'module.hrm')
            ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('hrm.view') && ! collect($permissions)->contains('crm.view')));

        $contextB = app(BusinessContextService::class)->build($member, $workspaceB);
        $this->assertSame('USD', $contextB['currency']['code']);
        $this->assertContains('hrm', $contextB['enabled_modules']);
        $this->assertNotContains('lead', $contextB['enabled_modules']);
    }

    private function role(Organization $organization, string $name, array $permissions): Role
    {
        $role = Role::create(['organization_id' => $organization->id, 'name' => $name, 'display_name' => $name, 'is_system' => false]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $role;
    }
}
