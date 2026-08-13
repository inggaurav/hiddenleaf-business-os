<?php

namespace App\Domain\Addons;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AddonManager
{
    public function install(AddonManifest $m): Addon
    {
        if (version_compare(config('modules.core_version'), $m->minimumCore, '<')) {
            throw new RuntimeException('Addon requires a newer core version.');
        }foreach ($m->dependencies as $dependency => $constraint) {
            $installed = Addon::where('alias', $dependency)->where('status', 'installed')->first();
            if (! $installed || ! version_compare($installed->version, ltrim($constraint, '>='), '>=')) {
                throw new RuntimeException("Addon dependency {$dependency} is not satisfied.");
            }
        }

return Addon::updateOrCreate(['addon_id' => $m->id], ['alias' => $m->alias, 'name' => $m->name, 'version' => $m->version, 'minimum_core' => $m->minimumCore, 'dependencies' => $m->dependencies, 'manifest' => $m->toArray(), 'status' => 'installed']);
    }

    public function activate(Workspace $workspace, Addon $addon, int $userId, array $config = []): void
    {
        $plan = $workspace->organization->plan_id ? Plan::find($workspace->organization->plan_id) : null;
        $allowed = $plan?->modules ?? [];
        if (! in_array($addon->alias, $allowed, true) && ! in_array('addon:*', $allowed, true)) {
            throw new RuntimeException('Addon is not included in the organization plan.');
        }$schema = $addon->manifest['settings'] ?? [];
        foreach ($config as $key => $value) {
            if (! array_key_exists($key, $schema)) {
                throw new RuntimeException("Unknown addon setting {$key}.");
            }
        }DB::table('workspace_addons')->updateOrInsert(['workspace_id' => $workspace->id, 'addon_id' => $addon->id], ['configuration' => json_encode($config), 'is_active' => true, 'activated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    }
}
