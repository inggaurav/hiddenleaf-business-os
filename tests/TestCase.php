<?php

namespace Tests;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give a test workspace the same explicit plan entitlement and activation
     * state required in production. Module middleware intentionally requires
     * both records; tests must not rely on an implicit all-modules fallback.
     *
     * @param  array<int, string>  $modules
     */
    protected function entitleWorkspaceModules(
        Organization $organization,
        Workspace $workspace,
        User $creator,
        array $modules,
    ): Plan {
        $modules = array_values(array_unique(array_map(
            static fn (string $module): string => strtolower($module),
            $modules,
        )));

        $plan = Plan::create([
            'name' => 'Test Workspace Plan '.$workspace->id,
            'modules' => $modules,
            'status' => true,
            'created_by' => $creator->id,
        ]);

        $organization->forceFill(['plan_id' => $plan->id])->save();

        foreach ($modules as $module) {
            UserActiveModule::firstOrCreate([
                'workspace_id' => $workspace->id,
                'module_name' => $module,
            ]);
        }

        return $plan;
    }
}
