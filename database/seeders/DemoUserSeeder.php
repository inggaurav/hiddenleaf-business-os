<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@hiddenleaf.io'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Enterprise Plan
        $plan = Plan::firstOrCreate(
            ['name' => 'Enterprise Plan'],
            [
                'description' => 'Unlimited access to all modules and advanced tools',
                'package_price_monthly' => 49.00,
                'package_price_yearly' => 490.00,
                'number_of_users' => 100,
                'workspace_limit' => 10,
                'storage_limit' => 10240,
                'modules' => json_encode(['account', 'hrm', 'lead', 'taskly', 'pos', 'landingpage', 'productservice']),
                'status' => true,
            ]
        );

        // 3. Create Company Admin
        $companyAdmin = User::firstOrCreate(
            ['email' => 'admin@acme.com'],
            [
                'name' => 'Acme Administrator',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 4. Create Organization
        $org = Organization::firstOrCreate(
            ['name' => 'Acme Corporation'],
            [
                'slug' => 'acme-corp',
                'owner_id' => $companyAdmin->id,
                'is_active' => true,
            ]
        );

        $org->members()->syncWithoutDetaching([
            $companyAdmin->id => ['role' => 'owner'],
        ]);

        // 5. Create Workspaces
        $ws1 = Workspace::firstOrCreate(
            ['name' => 'HQ Workspace', 'organization_id' => $org->id],
            [
                'slug' => 'hq-workspace',
                'created_by' => $companyAdmin->id,
            ]
        );

        $ws2 = Workspace::firstOrCreate(
            ['name' => 'Global Lab', 'organization_id' => $org->id],
            [
                'slug' => 'global-lab',
                'created_by' => $companyAdmin->id,
            ]
        );

        // 6. Assign Roles & Permissions in Workspace
        $adminRole = Role::where('name', 'workspace-admin')->first();
        if ($adminRole) {
            $companyAdmin->workspaces()->syncWithoutDetaching([
                $ws1->id => ['role_id' => $adminRole->id],
                $ws2->id => ['role_id' => $adminRole->id],
            ]);
        }

        // 7. Create Active Subscription
        Subscription::firstOrCreate(
            ['organization_id' => $org->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
            ]
        );

        // 8. Create Demo Team Member
        $member = User::firstOrCreate(
            ['email' => 'member@acme.com'],
            [
                'name' => 'Jane Smith',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $org->members()->syncWithoutDetaching([
            $member->id => ['role' => 'member'],
        ]);

        $memberRole = Role::where('name', 'workspace-member')->first();
        if ($memberRole) {
            $member->workspaces()->syncWithoutDetaching([
                $ws1->id => ['role_id' => $memberRole->id],
            ]);
        }

        // 9. Enable all modules for Demo Workspaces
        $allModules = ['account', 'hrm', 'lead', 'taskly', 'pos', 'landingpage', 'productservice', 'sales', 'procurement'];
        foreach ([$ws1, $ws2] as $ws) {
            foreach ($allModules as $mod) {
                \App\Models\UserActiveModule::firstOrCreate([
                    'workspace_id' => $ws->id,
                    'module_name' => $mod,
                ]);
            }
        }
    }
}
