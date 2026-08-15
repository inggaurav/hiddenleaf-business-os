<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AddonManager
{
    private const DEFAULT_CORE_MODULES = ['account', 'hrm', 'crm', 'lead', 'pos', 'taskly', 'productservice', 'landingpage'];

    /** @return Collection<int, Addon> */
    public function installed(): Collection
    {
        return Addon::query()
            ->whereIn(DB::raw('LOWER(status)'), ['installed', 'enabled', 'active'])
            ->orderBy('name')
            ->get();
    }

    public function find(string $alias): ?Addon
    {
        return Addon::query()->whereRaw('LOWER(alias) = ?', [strtolower($alias)])->first();
    }

    public function isPlanEntitled(Workspace $workspace, string $alias): bool
    {
        $planId = $workspace->organization?->plan_id;
        $plan = $planId ? Plan::find($planId) : null;
        if (! $plan) { return false; }

        $needle = strtolower($alias);
        $modules = array_map(static fn (mixed $module): string => strtolower((string) $module), $plan->modules ?? []);
        return in_array($needle, $modules, true);
    }

    public function isActiveForWorkspace(Workspace $workspace, string $alias): bool
    {
        $alias = strtolower($alias);
        $addon = $this->find($alias);

        if ($addon) {
            return WorkspaceAddon::query()
                ->where('workspace_id', $workspace->id)
                ->where('addon_id', $addon->id)
                ->where('is_active', true)
                ->exists();
        }

        $activeQuery = UserActiveModule::query()->where('workspace_id', $workspace->id);
        if (! (clone $activeQuery)->exists()) {
            return in_array($alias, self::DEFAULT_CORE_MODULES, true);
        }

        return $activeQuery->whereRaw('LOWER(module_name) = ?', [$alias])->exists();
    }

    public function canUse(Workspace $workspace, string $alias, bool $superAdmin = false): bool
    {
        $alias = strtolower($alias);
        $addon = $this->find($alias);

        if ($addon && ! in_array(strtolower((string) $addon->status), ['installed', 'enabled', 'active'], true)) {
            return false;
        }
        if (! $this->isActiveForWorkspace($workspace, $alias)) { return false; }

        return $superAdmin || $this->isPlanEntitled($workspace, $alias);
    }

    public function activate(Workspace $workspace, string $alias, ?int $activatedBy = null, bool $superAdmin = false): WorkspaceAddon
    {
        $addon = $this->find($alias);
        if (! $addon || ! in_array(strtolower((string) $addon->status), ['installed', 'enabled', 'active'], true)) {
            throw new RuntimeException("Add-on {$alias} is not installed.");
        }
        if (! $superAdmin && ! $this->isPlanEntitled($workspace, $addon->alias)) {
            throw new RuntimeException("Add-on {$addon->name} is not included in this plan.");
        }

        $module = new ManifestAddonModule($addon);
        foreach ($module->dependencies() as $dependency) {
            if (! $this->canUse($workspace, $dependency, $superAdmin)) {
                throw new RuntimeException("Add-on {$addon->name} requires {$dependency} to be enabled first.");
            }
        }

        return WorkspaceAddon::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'addon_id' => $addon->id],
            ['is_active' => true, 'activated_by' => $activatedBy]
        );
    }

    public function deactivate(Workspace $workspace, string $alias): void
    {
        $addon = $this->find($alias);
        if (! $addon) { throw new RuntimeException("Add-on {$alias} is not installed."); }

        $activeDependents = $this->installed()->filter(function (Addon $candidate) use ($workspace, $addon): bool {
            if ($candidate->id === $addon->id) { return false; }
            return in_array(strtolower($addon->alias), (new ManifestAddonModule($candidate))->dependencies(), true)
                && $this->isActiveForWorkspace($workspace, $candidate->alias);
        })->pluck('name')->all();

        if ($activeDependents !== []) {
            throw new RuntimeException('Disable dependent add-ons first: '.implode(', ', $activeDependents));
        }

        WorkspaceAddon::query()
            ->where('workspace_id', $workspace->id)
            ->where('addon_id', $addon->id)
            ->update(['is_active' => false]);
    }

    /** @return array<string, array<int, class-string>> */
    public function capabilitiesForWorkspace(Workspace $workspace, string $capability, bool $superAdmin = false): array
    {
        $result = [];
        foreach ($this->installed() as $addon) {
            if (! $this->canUse($workspace, $addon->alias, $superAdmin)) { continue; }
            $module = new ManifestAddonModule($addon);
            $classes = match ($capability) {
                'mrfox.tools' => $module->mrFoxTools(),
                'automations.triggers' => $module->automationTriggers(),
                'automations.actions' => $module->automationActions(),
                'search.providers' => $module->searchProviders(),
                'command_center.signals' => $module->commandCenterSignals(),
                default => [],
            };
            if ($classes !== []) { $result[$module->getAlias()] = $classes; }
        }
        return $result;
    }
}
