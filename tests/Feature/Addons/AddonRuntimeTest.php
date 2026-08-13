<?php

namespace Tests\Feature\Addons;

use App\Domain\Addons\AddonManager;
use App\Domain\Addons\AddonManifest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AddonRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependencies_entitlement_and_configuration_are_enforced(): void
    {
        $manager = app(AddonManager::class);
        $base = $manager->install(AddonManifest::from(['id' => 'base', 'alias' => 'base-addon', 'name' => 'Base', 'version' => '1.2.0', 'minimum_core' => '1.0.0', 'dependencies' => []]));
        $addon = $manager->install(AddonManifest::from(['id' => 'reports', 'alias' => 'reports-addon', 'name' => 'Reports', 'version' => '1.0.0', 'minimum_core' => '1.0.0', 'dependencies' => ['base-addon' => '>=1.0.0'], 'settings' => ['region' => ['type' => 'string']]]));
        $owner = User::factory()->create();
        $plan = Plan::create(['name' => 'Addon', 'modules' => ['reports-addon'], 'status' => true, 'created_by' => $owner->id]);
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id]);
        $manager->activate($ws, $addon, $owner->id, ['region' => 'IN']);
        $this->assertDatabaseHas('workspace_addons', ['workspace_id' => $ws->id, 'addon_id' => $addon->id, 'is_active' => true]);
        $this->assertSame('installed', $base->status);
    }

    public function test_missing_dependency_and_unknown_configuration_fail(): void
    {
        $manager = app(AddonManager::class);
        $this->expectException(RuntimeException::class);
        $manager->install(AddonManifest::from(['id' => 'broken', 'alias' => 'broken-addon', 'name' => 'Broken', 'version' => '1.0.0', 'minimum_core' => '1.0.0', 'dependencies' => ['missing' => '>=1.0.0']]));
    }
}
