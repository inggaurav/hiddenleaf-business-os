<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'view', 'name' => 'workspace.view'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'create', 'name' => 'workspace.create'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'update', 'name' => 'workspace.update'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'delete', 'name' => 'workspace.delete'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'switch', 'name' => 'workspace.switch'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'view', 'name' => 'workspace.members.view'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'invite', 'name' => 'workspace.members.invite'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'remove', 'name' => 'workspace.members.remove'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'assign_role', 'name' => 'workspace.members.assign_role'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'view', 'name' => 'roles.view'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'create', 'name' => 'roles.create'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'update', 'name' => 'roles.update'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'delete', 'name' => 'roles.delete'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'assign_permissions', 'name' => 'roles.assign_permissions'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'change_password', 'name' => 'users.change_password'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'toggle_status', 'name' => 'users.toggle_status'],
            ['module' => 'modules', 'resource' => 'modules', 'action' => 'manage', 'name' => 'modules.manage'],
            ['module' => 'productservice', 'resource' => 'catalog', 'action' => 'manage', 'name' => 'product_service.manage'],
            ['module' => 'productservice', 'resource' => 'inventory', 'action' => 'adjust', 'name' => 'inventory.adjust'],
            ['module' => 'productservice', 'resource' => 'inventory', 'action' => 'manage', 'name' => 'inventory.manage'],
            ['module' => 'sales', 'resource' => 'sales', 'action' => 'manage', 'name' => 'sales.manage'],
            ['module' => 'procurement', 'resource' => 'purchases', 'action' => 'manage', 'name' => 'procurement.manage'],
            // Granular ProductService & Inventory permissions for WorkDo parity
            ...$this->productServicePermissions(),

            // Legacy Account umbrella permissions retained for existing tenants.
            ['module' => 'account', 'resource' => 'ledger', 'action' => 'view', 'name' => 'account.view'],
            ['module' => 'account', 'resource' => 'ledger', 'action' => 'manage', 'name' => 'account.manage'],

            // Granular Account permissions for WorkDo-equivalent workflows.
            ...$this->accountPermissions(),

            ['module' => 'hrm', 'resource' => 'hr', 'action' => 'view', 'name' => 'hrm.view'],
            ['module' => 'hrm', 'resource' => 'hr', 'action' => 'manage', 'name' => 'hrm.manage'],
            ['module' => 'lead', 'resource' => 'crm', 'action' => 'view', 'name' => 'crm.view'],
            ['module' => 'lead', 'resource' => 'crm', 'action' => 'manage', 'name' => 'crm.manage'],
            ['module' => 'taskly', 'resource' => 'projects', 'action' => 'view', 'name' => 'taskly.view'],
            ['module' => 'taskly', 'resource' => 'projects', 'action' => 'manage', 'name' => 'taskly.manage'],
            ['module' => 'pos', 'resource' => 'registers', 'action' => 'manage', 'name' => 'pos.manage'],
            ...$this->posPermissions(),
            ['module' => 'landingpage', 'resource' => 'landing', 'action' => 'manage', 'name' => 'landing.manage'],
            ['module' => 'core', 'resource' => 'webhooks', 'action' => 'manage', 'name' => 'webhooks.manage'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $adminRole = Role::firstOrCreate(
            ['name' => 'workspace-admin', 'organization_id' => null],
            ['display_name' => 'Workspace Admin', 'is_system' => true]
        );
        $memberRole = Role::firstOrCreate(
            ['name' => 'workspace-member', 'organization_id' => null],
            ['display_name' => 'Workspace Member', 'is_system' => true]
        );

        $adminRole->permissions()->sync(Permission::pluck('id')->toArray());
        $memberRole->permissions()->sync(
            Permission::whereIn('name', ['workspace.view', 'workspace.switch', 'workspace.members.view'])->pluck('id')->toArray()
        );

        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@hiddenleaf.test'],
            [
                'name' => 'HiddenLeaf Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]
        );

        $plan = \App\Models\Plan::firstOrCreate(
            ['name' => 'Enterprise'],
            ['modules' => ['lead', 'account', 'taskly'], 'created_by' => $user->id]
        );

        $org = \App\Models\Organization::firstOrCreate(
            ['slug' => 'hiddenleaf-demo'],
            ['name' => 'HiddenLeaf Demo', 'owner_id' => $user->id, 'plan_id' => $plan->id]
        );

        $ws = \App\Models\Workspace::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Main Workspace'],
            ['slug' => 'main', 'created_by' => $user->id]
        );

        \Illuminate\Support\Facades\DB::table('organization_memberships')->updateOrInsert(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()]
        );

        \Illuminate\Support\Facades\DB::table('workspace_memberships')->updateOrInsert(
            ['workspace_id' => $ws->id, 'user_id' => $user->id],
            ['role_id' => $adminRole->id, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function accountPermissions(): array
    {
        $definitions = [
            'dashboard' => ['view'],
            'customer' => ['view', 'create', 'update', 'delete'],
            'vendor' => ['view', 'create', 'update', 'delete'],
            'bank_account' => ['view', 'create', 'update', 'delete'],
            'account_type' => ['view', 'create', 'update', 'delete'],
            'ledger_account' => ['view', 'create', 'update', 'delete'],
            'journal' => ['view', 'create', 'post', 'reverse'],
            'customer_payment' => ['view', 'create', 'void'],
            'vendor_payment' => ['view', 'create', 'void'],
            'bank_transaction' => ['view', 'reconcile'],
            'bank_transfer' => ['view', 'create', 'update', 'delete', 'process'],
            'revenue_category' => ['view', 'create', 'update', 'delete'],
            'expense_category' => ['view', 'create', 'update', 'delete'],
            'revenue' => ['view', 'create', 'update', 'delete', 'approve', 'post'],
            'expense' => ['view', 'create', 'update', 'delete', 'approve', 'post'],
            'credit_note' => ['view', 'create', 'approve', 'delete'],
            'debit_note' => ['view', 'create', 'approve', 'delete'],
            'report' => ['view', 'print'],
        ];

        $rows = [];
        foreach ($definitions as $resource => $actions) {
            foreach ($actions as $action) {
                $rows[] = [
                    'module' => 'account',
                    'resource' => $resource,
                    'action' => $action,
                    'name' => "account.{$resource}.{$action}",
                ];
            }
        }

        return $rows;
    }

    private function productServicePermissions(): array
    {
        $definitions = [
            'item' => ['view', 'create', 'update', 'delete'],
            'category' => ['view', 'create', 'update', 'delete'],
            'unit' => ['view', 'create', 'update', 'delete'],
            'tax' => ['view', 'create', 'update', 'delete'],
        ];

        $rows = [];
        foreach ($definitions as $resource => $actions) {
            foreach ($actions as $action) {
                $rows[] = [
                    'module' => 'productservice',
                    'resource' => $resource,
                    'action' => $action,
                    'name' => "product_service.{$resource}.{$action}",
                ];
            }
        }

        $invDefinitions = [
            'warehouse' => ['view', 'create', 'update', 'delete'],
            'transfer' => ['view', 'create', 'delete'],
            'stock' => ['view', 'adjust'],
            'movement' => ['view'],
            'report' => ['view'],
        ];

        foreach ($invDefinitions as $resource => $actions) {
            foreach ($actions as $action) {
                $rows[] = [
                    'module' => 'inventory',
                    'resource' => $resource,
                    'action' => $action,
                    'name' => "inventory.{$resource}.{$action}",
                ];
            }
        }

        return $rows;
    }

    private function posPermissions(): array
    {
        $definitions = [
            'dashboard' => ['view'],
            'order' => ['view', 'create', 'print'],
            'checkout' => ['execute'],
            'counter' => ['view', 'create', 'update', 'delete'],
            'discount' => ['view', 'create', 'update', 'delete'],
            'report' => ['view'],
            'return' => ['view', 'create', 'approve', 'complete', 'delete'],
            'barcode' => ['view', 'print'],
        ];

        $rows = [];
        foreach ($definitions as $resource => $actions) {
            foreach ($actions as $action) {
                $rows[] = [
                    'module' => 'pos',
                    'resource' => $resource,
                    'action' => $action,
                    'name' => "pos.{$resource}.{$action}",
                ];
            }
        }

        return $rows;
    }
}
