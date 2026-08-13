<?php

namespace Tests\Feature\Modules;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleEngineSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $companyAdmin;

    protected Organization $org;

    protected Workspace $ws;

    protected ModuleManager $moduleManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'super@hiddenleaf.test',
        ]);

        $this->companyAdmin = User::factory()->create([
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

        $this->moduleManager = app(ModuleManager::class);
    }

    public function test_all_seven_bundled_modules_are_discovered_and_registered(): void
    {
        $modules = $this->moduleManager->getAllModules();

        $expectedAliases = ['account', 'hrm', 'lead', 'taskly', 'pos', 'productservice', 'landingpage'];

        foreach ($expectedAliases as $alias) {
            $this->assertArrayHasKey($alias, $modules, "Module {$alias} must be registered.");
            $mod = $this->moduleManager->getModule($alias);
            $this->assertNotNull($mod);
            $this->assertNotEmpty($mod->getName());
            $this->assertNotEmpty($mod->getPermissions());
            $this->assertNotEmpty($mod->getNavigation());
        }
    }

    public function test_workspace_module_activation_and_deactivation(): void
    {
        // 1. Initially Account is not enabled in workspace
        $this->assertFalse($this->moduleManager->isModuleEnabledForWorkspace('account', $this->ws->id));

        // 2. Enable Account module
        $resp = $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/modules/toggle', [
                'module_name' => 'account',
                'active' => true,
            ]);

        $resp->assertSessionHas('success');
        $this->assertTrue($this->moduleManager->isModuleEnabledForWorkspace('account', $this->ws->id));

        // 3. Disable Account module
        $resp2 = $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/modules/toggle', [
                'module_name' => 'account',
                'active' => false,
            ]);

        $resp2->assertSessionHas('success');
        $this->assertFalse($this->moduleManager->isModuleEnabledForWorkspace('account', $this->ws->id));
    }

    public function test_plan_entitlement_checking(): void
    {
        $plan = Plan::create([
            'name' => 'CRM + Projects Bundle',
            'package_price_monthly' => 45.00,
            'package_price_yearly' => 450.00,
            'number_of_users' => 10,
            'storage_limit' => 1024 * 1024,
            'modules' => ['lead', 'taskly'],
            'status' => true,
        ]);

        $this->assertTrue($this->moduleManager->isModuleIncludedInPlan('lead', $plan->id));
        $this->assertTrue($this->moduleManager->isModuleIncludedInPlan('taskly', $plan->id));
        $this->assertFalse($this->moduleManager->isModuleIncludedInPlan('account', $plan->id));
    }
}
