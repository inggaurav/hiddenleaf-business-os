<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MrFoxBrandProfile;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a complete, production-ready organization and workspace for a customer.
     * Guaranteed to be atomic and idempotent.
     */
    public function provision(User $owner, array $attributes = []): array
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $companyName = $attributes['company_name'] ?? ($owner->name . "'s Organization");
            $currency = $attributes['currency'] ?? 'USD';
            $currencySymbol = $attributes['currency_symbol'] ?? '$';
            $timezone = $attributes['timezone'] ?? 'UTC';

            // 1. Get or create Default Trial Plan (14 Days)
            $plan = Plan::where('name', 'Starter')->first();
            if (! $plan) {
                $plan = Plan::create([
                    'name' => 'Starter',
                    'price' => 0.00,
                    'duration' => 'month',
                    'max_users' => 10,
                    'max_workspaces' => 2,
                    'trial_days' => 14,
                    'modules' => [
                        'account', 'crm', 'hrm', 'productservice', 'taskly',
                        'pos', 'communications', 'automations', 'missions', 'command_center'
                    ],
                    'status' => true,
                    'created_by' => $owner->id,
                ]);
            }

            // 2. Create Organization
            $org = Organization::create([
                'name' => $companyName,
                'slug' => Str::slug($companyName) . '-' . Str::random(4),
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
                'trial_ends_at' => now()->addDays(14),
                'status' => 'active',
            ]);

            // 3. Create Primary Workspace
            $ws = Workspace::create([
                'name' => 'Primary Workspace',
                'slug' => Str::slug($companyName) . '-' . Str::random(4),
                'organization_id' => $org->id,
                'created_by' => $owner->id,
                'is_active' => true,
            ]);

            // 4. Attach Memberships
            $org->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
            $ws->members()->syncWithoutDetaching([$owner->id]);

            // 5. Seed Standard Roles & Templates
            $this->seedRoleTemplates($org, $ws, $owner);

            // 6. Seed Default System Settings
            $this->seedDefaultSettings($org, $ws, $owner, [
                'currency' => $currency,
                'currency_symbol' => $currencySymbol,
                'timezone' => $timezone,
                'company_name' => $companyName,
            ]);

            // 7. Seed Initial Brand Profile Placeholder
            MrFoxBrandProfile::firstOrCreate([
                'organization_id' => $org->id,
                'workspace_id' => $ws->id,
            ], [
                'name' => $companyName,
                'industry' => $attributes['industry'] ?? 'Technology',
                'mission' => $attributes['description'] ?? 'Modern enterprise operating on HiddenLeaf Business OS.',
                'target_audience' => [$attributes['target_audience'] ?? 'General Business Clients'],
                'tone_of_voice' => ['professional', 'decisive', 'helpful'],
                'is_default' => true,
                'created_by' => $owner->id,
            ]);

            // 8. Seed Default Warehouse
            Warehouse::firstOrCreate([
                'organization_id' => $org->id,
                'workspace_id' => $ws->id,
                'name' => 'Main Warehouse',
            ], [
                'address' => 'HQ Facility',
                'city' => 'Primary',
                'zip_code' => '00000',
            ]);

            return [
                'organization' => $org,
                'workspace' => $ws,
                'plan' => $plan,
            ];
        });
    }

    /**
     * Seeds role templates for the newly created tenant.
     */
    private function seedRoleTemplates(Organization $org, Workspace $ws, User $owner): void
    {
        $roles = [
            'Owner' => 'Full administrative control over all organization resources and financial records.',
            'Admin' => 'Operational administrative rights across modules.',
            'Finance' => 'Accounting, invoicing, expenses, payables, and revenue management.',
            'Sales Manager' => 'CRM pipeline, leads, deals, quotes, and communications oversight.',
            'Sales' => 'Leads, deal execution, customer records, and messaging.',
            'HR Manager' => 'Employee records, attendance, payroll, and leave management.',
            'Project Manager' => 'Taskly projects, milestones, task assignments, and time logs.',
            'Team Member' => 'General task completion and internal messaging.',
            'Viewer' => 'Read-only access across assigned operational modules.',
        ];

        foreach ($roles as $roleName => $desc) {
            Role::firstOrCreate([
                'organization_id' => $org->id,
                'name' => Str::slug($roleName),
            ], [
                'display_name' => $roleName,
                'is_system' => false,
            ]);
        }
    }

    /**
     * Seeds default hierarchical settings for tenant.
     */
    private function seedDefaultSettings(Organization $org, Workspace $ws, User $owner, array $config): void
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
            'mrfox_default_model' => 'gemini-1.5-pro',
            'onboarding_completed' => '0',
            'onboarding_current_step' => '1',
        ];

        foreach ($defaultSettings as $key => $val) {
            Setting::firstOrCreate([
                'scope' => 'workspace',
                'scope_id' => $ws->id,
                'key' => $key,
            ], [
                'organization_id' => $org->id,
                'workspace_id' => $ws->id,
                'value' => $val,
                'is_encrypted' => false,
            ]);
        }
    }
}
