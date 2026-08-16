<?php

namespace App\Services;

use App\Models\MrFoxBrandProfile;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a complete organization and primary workspace.
     * The operation is atomic and uses the current SaaS, RBAC and module schemas.
     */
    public function provision(User $owner, array $attributes = []): array
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $companyName = $attributes['company_name'] ?? ($owner->name."'s Organization");
            $currency = $attributes['currency'] ?? 'USD';
            $currencySymbol = $attributes['currency_symbol'] ?? '$';
            $timezone = $attributes['timezone'] ?? 'UTC';

            $plan = $this->starterPlan($owner);

            $organization = Organization::firstOrCreate(
                ['owner_id' => $owner->id, 'name' => $companyName],
                [
                    'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(6)),
                    'plan_id' => $plan->id,
                    'plan_expires_at' => now()->addDays(max(1, (int) $plan->trial_days)),
                    'is_active' => true,
                    'settings' => [],
                ]
            );

            $organization->forceFill([
                'plan_id' => $organization->plan_id ?: $plan->id,
                'is_active' => true,
            ])->save();

            $workspace = Workspace::firstOrCreate(
                ['organization_id' => $organization->id, 'created_by' => $owner->id],
                [
                    'name' => 'Primary Workspace',
                    'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(6)),
                    'is_active' => true,
                ]
            );

            $roles = $this->seedRoleTemplates($organization);
            $ownerRole = $roles['Owner'];

            $organization->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
            $workspace->members()->syncWithoutDetaching([$owner->id => ['role_id' => $ownerRole->id]]);

            foreach ($plan->modules ?? [] as $module) {
                UserActiveModule::firstOrCreate([
                    'workspace_id' => $workspace->id,
                    'module_name' => strtolower((string) $module),
                ], [
                    'user_id' => $owner->id,
                ]);
            }

            Subscription::updateOrCreate(
                ['organization_id' => $organization->id, 'plan_id' => $plan->id],
                [
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => $plan->trial ? now()->addDays(max(1, (int) $plan->trial_days)) : null,
                ]
            );

            $this->seedDefaultSettings($organization, $workspace, [
                'currency' => $currency,
                'currency_symbol' => $currencySymbol,
                'timezone' => $timezone,
                'company_name' => $companyName,
            ]);

            MrFoxBrandProfile::firstOrCreate([
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
            ], [
                'name' => $companyName,
                'industry' => $attributes['industry'] ?? 'Technology',
                'mission' => $attributes['description'] ?? 'Modern enterprise operating on HiddenLeaf Business OS.',
                'target_audience' => [$attributes['target_audience'] ?? 'General Business Clients'],
                'tone_of_voice' => ['professional', 'decisive', 'helpful'],
                'is_default' => true,
                'created_by' => $owner->id,
            ]);

            Warehouse::firstOrCreate([
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'name' => 'Main Warehouse',
            ], [
                'address' => 'HQ Facility',
                'city' => 'Primary',
                'zip_code' => '00000',
            ]);

            return [
                'organization' => $organization,
                'workspace' => $workspace,
                'plan' => $plan,
                'roles' => $roles,
            ];
        });
    }

    private function starterPlan(User $owner): Plan
    {
        $plan = Plan::where('name', 'Starter')->first();
        if ($plan) {
            return $plan;
        }

        return Plan::create([
            'name' => 'Starter',
            'description' => 'Default onboarding trial. Pricing and commercial packaging remain configurable by Super Admin.',
            'package_price_monthly' => 0,
            'package_price_yearly' => 0,
            'price_per_user_monthly' => 0,
            'price_per_user_yearly' => 0,
            'price_per_storage_monthly' => 0,
            'price_per_storage_yearly' => 0,
            'number_of_users' => 10,
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'workspace_limit' => 2,
            'modules' => [
                'account', 'crm', 'lead', 'hrm', 'productservice', 'taskly', 'pos',
                'sales', 'procurement', 'communications', 'automations', 'missions', 'command_center',
            ],
            'trial' => true,
            'trial_days' => 14,
            'free_plan' => false,
            'status' => true,
            'custom_plan' => false,
            'created_by' => $owner->id,
        ]);
    }

    /** @return array<string, Role> */
    private function seedRoleTemplates(Organization $organization): array
    {
        $templates = [
            'Owner' => ['*'],
            'Admin' => ['*'],
            'Finance' => ['workspace.view', 'workspace.switch', 'account.', 'sales.', 'procurement.', 'product_service.', 'inventory.'],
            'Sales Manager' => ['workspace.view', 'workspace.switch', 'crm.', 'sales.', 'product_service.item.view', 'inventory.stock.view'],
            'Sales' => ['workspace.view', 'workspace.switch', 'crm.view', 'crm.manage', 'sales.manage'],
            'HR Manager' => ['workspace.view', 'workspace.switch', 'hrm.'],
            'Project Manager' => ['workspace.view', 'workspace.switch', 'taskly.'],
            'Team Member' => ['workspace.view', 'workspace.switch', 'taskly.view'],
            'Viewer' => ['workspace.view', 'workspace.switch', '.view'],
        ];

        $allPermissions = Permission::query()->get(['id', 'name']);
        $result = [];

        foreach ($templates as $roleName => $patterns) {
            $role = Role::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => Str::slug($roleName),
            ], [
                'display_name' => $roleName,
                'is_system' => false,
            ]);

            $ids = $patterns === ['*']
                ? $allPermissions->pluck('id')->all()
                : $allPermissions->filter(function ($permission) use ($patterns) {
                    foreach ($patterns as $pattern) {
                        if ($pattern === $permission->name) {
                            return true;
                        }
                        if (str_ends_with($pattern, '.') && str_starts_with($permission->name, $pattern)) {
                            return true;
                        }
                        if (str_starts_with($pattern, '.') && str_ends_with($permission->name, $pattern)) {
                            return true;
                        }
                    }

                    return false;
                })->pluck('id')->all();

            $role->permissions()->sync($ids);
            $result[$roleName] = $role;
        }

        return $result;
    }

    private function seedDefaultSettings(Organization $organization, Workspace $workspace, array $config): void
    {
        $defaultSettings = [
            'app_name' => 'HiddenLeaf Business OS',
            'company_name' => $config['company_name'],
            'site_currency' => $config['currency'],
            'site_currency_symbol' => $config['currency_symbol'],
            'timezone' => $config['timezone'],
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'mrfox_enabled' => '1',
            'onboarding_completed' => '0',
            'onboarding_current_step' => '1',
        ];

        foreach ($defaultSettings as $key => $value) {
            Setting::updateOrCreate([
                'scope' => 'workspace',
                'scope_id' => $workspace->id,
                'key' => $key,
            ], [
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'value' => $value,
                'is_encrypted' => false,
            ]);
        }
    }
}
