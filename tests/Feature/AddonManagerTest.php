<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\AddonManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_with_only_user_active_modules_row_passes_can_use_after_addon_created()
    {
        $plan = Plan::create([
            'name' => 'Test Plan',
            'modules' => ['test_module'],
            'price_per_user_monthly' => 0,
            'price_per_user_yearly' => 0,
            'price_per_storage_monthly' => 0,
            'price_per_storage_yearly' => 0,
            'number_of_users' => 10,
            'storage_limit' => 10,
            'workspace_limit' => 1,
            'status' => 1,
            'created_by' => 1,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'is_active' => 1,
        ]);
        $workspace = Workspace::create([
            'organization_id' => $org->id,
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
            'created_by' => $user->id,
            'is_active' => 1,
        ]);

        // Legacy active module row
        UserActiveModule::create([
            'workspace_id' => $workspace->id,
            'module_name' => 'test_module',
            'is_active' => 1,
        ]);

        $manager = new AddonManager();

        // 1. Without addon record, it should use the legacy path
        $this->assertTrue($manager->canUse($workspace, 'test_module'));

        // 2. Create the addon record (but no WorkspaceAddon row yet)
        Addon::create([
            'addon_id' => 'test-addon',
            'alias' => 'test_module',
            'name' => 'Test Addon',
            'version' => '1.0.0',
            'minimum_core' => '1.0.0',
            'status' => 'installed',
            'dependencies' => [],
            'manifest' => [],
        ]);

        // 3. Even with addon record, the legacy row should fall back and return true
        $this->assertTrue($manager->canUse($workspace, 'test_module'));
    }
}
