<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\SettingsSectionRegistry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsRegistryRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_covers_the_actual_workdo_core_and_payment_setting_keys(): void
    {
        $keys = collect(app(SettingsSectionRegistry::class)->sections())
            ->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'))
            ->unique();

        foreach (['logo_dark', 'calendarStartDay', 'strictlyNecessaryCookies', 'awsEndpoint', 'wasabiSecretKey', 'stripe_secret', 'paypal_secret_key', 'ai_agent_provider'] as $key) {
            $this->assertTrue($keys->contains($key), "Missing WorkDo setting key {$key}");
        }

        $this->assertGreaterThanOrEqual(86, $keys->count());
    }

    public function test_team_admin_only_sees_and_mutates_granted_settings_sections(): void
    {
        $this->seed();
        [$owner, $member, $organization, $workspace] = $this->tenant();
        $role = $this->roleWith($organization, ['settings.view', 'settings.localization.manage']);
        $member->workspaces()->attach($workspace, ['role_id' => $role->id]);

        $request = $this->actingAs($member)->withSession([
            'active_organization_id' => $organization->id,
            'active_workspace_id' => $workspace->id,
        ]);

        $request->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->where('settingsSections.0.id', 'company.localization')
            ->missing('settingsSections.1'));

        $request->post('/settings', [
            '_section' => 'company.localization',
            'values' => ['timezone' => 'Asia/Kolkata'],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'scope' => 'workspace',
            'scope_id' => $workspace->id,
            'key' => 'timezone',
            'value' => 'Asia/Kolkata',
        ]);

        $request->post('/settings', [
            '_section' => 'company.identity',
            'values' => ['company_name' => 'Escalated'],
        ])->assertForbidden();
    }

    public function test_ordinary_team_member_cannot_open_administrative_settings(): void
    {
        $this->seed();
        [, $member, $organization, $workspace] = $this->tenant();
        $member->workspaces()->attach($workspace);

        $this->actingAs($member)->withSession([
            'active_organization_id' => $organization->id,
            'active_workspace_id' => $workspace->id,
        ])->get('/settings')->assertForbidden();
    }

    public function test_module_settings_require_entitlement_activation_and_permission(): void
    {
        $this->seed();
        [$owner, $member, $organization, $workspace] = $this->tenant();
        $role = $this->roleWith($organization, ['settings.view', 'settings.modules.manage']);
        $member->workspaces()->attach($workspace, ['role_id' => $role->id]);
        $this->entitleWorkspaceModules($organization, $workspace, $owner, ['hrm']);

        $request = $this->actingAs($member)->withSession([
            'active_organization_id' => $organization->id,
            'active_workspace_id' => $workspace->id,
        ]);

        $request->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->has('settingsSections', fn (Assert $sections) => $sections
                ->where('0.id', 'module.hrm')
                ->etc()));

        UserActiveModule::where('workspace_id', $workspace->id)->where('module_name', 'hrm')->delete();

        $request->post('/settings', [
            '_section' => 'module.hrm',
            'values' => ['hrm.work_week' => 'Monday-Friday'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('settings', ['key' => 'hrm.work_week']);
    }

    /** @return array{User, User, Organization, Workspace} */
    private function tenant(): array
    {
        $owner = User::factory()->create(['role' => 'company_admin']);
        $member = User::factory()->create(['role' => 'user']);
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
        $organization->members()->attach($owner, ['role' => 'owner']);
        $organization->members()->attach($member, ['role' => 'member']);
        $workspace->members()->attach($owner);

        return [$owner, $member, $organization, $workspace];
    }

    /** @param array<int, string> $permissions */
    private function roleWith(Organization $organization, array $permissions): Role
    {
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'selected-settings-admin',
            'display_name' => 'Selected Settings Admin',
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $role;
    }
}
