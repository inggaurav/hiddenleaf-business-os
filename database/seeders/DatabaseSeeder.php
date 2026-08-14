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
            // Workspace Permissions
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'view', 'name' => 'workspace.view'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'create', 'name' => 'workspace.create'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'update', 'name' => 'workspace.update'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'delete', 'name' => 'workspace.delete'],
            ['module' => 'workspace', 'resource' => 'workspace', 'action' => 'switch', 'name' => 'workspace.switch'],

            // Member Management Permissions
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'view', 'name' => 'workspace.members.view'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'invite', 'name' => 'workspace.members.invite'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'remove', 'name' => 'workspace.members.remove'],
            ['module' => 'workspace', 'resource' => 'members', 'action' => 'assign_role', 'name' => 'workspace.members.assign_role'],

            // RBAC Role Management Permissions
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'view', 'name' => 'roles.view'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'create', 'name' => 'roles.create'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'update', 'name' => 'roles.update'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'delete', 'name' => 'roles.delete'],
            ['module' => 'rbac', 'resource' => 'roles', 'action' => 'assign_permissions', 'name' => 'roles.assign_permissions'],

            // User Administration Permissions
            ['module' => 'admin', 'resource' => 'users', 'action' => 'view', 'name' => 'users.view'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'create', 'name' => 'users.create'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'update', 'name' => 'users.update'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'delete', 'name' => 'users.delete'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'change_password', 'name' => 'users.change_password'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'toggle_status', 'name' => 'users.toggle_status'],
            ['module' => 'admin', 'resource' => 'users', 'action' => 'impersonate', 'name' => 'users.impersonate'],

            // Sales Invoices
            ['module' => 'sales', 'resource' => 'invoices', 'action' => 'view', 'name' => 'sales.invoice.view'],
            ['module' => 'sales', 'resource' => 'invoices', 'action' => 'create', 'name' => 'sales.invoice.create'],
            ['module' => 'sales', 'resource' => 'invoices', 'action' => 'update', 'name' => 'sales.invoice.update'],
            ['module' => 'sales', 'resource' => 'invoices', 'action' => 'delete', 'name' => 'sales.invoice.delete'],

            // Sales Proposals
            ['module' => 'sales', 'resource' => 'proposals', 'action' => 'view', 'name' => 'sales.proposal.view'],
            ['module' => 'sales', 'resource' => 'proposals', 'action' => 'create', 'name' => 'sales.proposal.create'],
            ['module' => 'sales', 'resource' => 'proposals', 'action' => 'update', 'name' => 'sales.proposal.update'],
            ['module' => 'sales', 'resource' => 'proposals', 'action' => 'delete', 'name' => 'sales.proposal.delete'],

            // Sales Returns
            ['module' => 'sales', 'resource' => 'returns', 'action' => 'view', 'name' => 'sales.return.view'],
            ['module' => 'sales', 'resource' => 'returns', 'action' => 'create', 'name' => 'sales.return.create'],
            ['module' => 'sales', 'resource' => 'returns', 'action' => 'update', 'name' => 'sales.return.update'],
            ['module' => 'sales', 'resource' => 'returns', 'action' => 'delete', 'name' => 'sales.return.delete'],

            // Orders
            ['module' => 'billing', 'resource' => 'orders', 'action' => 'view', 'name' => 'orders.view'],
            ['module' => 'billing', 'resource' => 'orders', 'action' => 'delete', 'name' => 'orders.delete'],

            // Warehouses & Inventory
            ['module' => 'inventory', 'resource' => 'warehouses', 'action' => 'view', 'name' => 'warehouses.view'],
            ['module' => 'inventory', 'resource' => 'warehouses', 'action' => 'create', 'name' => 'warehouses.create'],
            ['module' => 'inventory', 'resource' => 'warehouses', 'action' => 'update', 'name' => 'warehouses.update'],
            ['module' => 'inventory', 'resource' => 'warehouses', 'action' => 'delete', 'name' => 'warehouses.delete'],

            // Inventory Transfers
            ['module' => 'inventory', 'resource' => 'transfers', 'action' => 'view', 'name' => 'transfers.view'],
            ['module' => 'inventory', 'resource' => 'transfers', 'action' => 'create', 'name' => 'transfers.create'],
            ['module' => 'inventory', 'resource' => 'transfers', 'action' => 'delete', 'name' => 'transfers.delete'],

            // Purchase Invoices
            ['module' => 'procurement', 'resource' => 'invoices', 'action' => 'view', 'name' => 'purchase.invoice.view'],
            ['module' => 'procurement', 'resource' => 'invoices', 'action' => 'create', 'name' => 'purchase.invoice.create'],
            ['module' => 'procurement', 'resource' => 'invoices', 'action' => 'update', 'name' => 'purchase.invoice.update'],
            ['module' => 'procurement', 'resource' => 'invoices', 'action' => 'delete', 'name' => 'purchase.invoice.delete'],

            // Purchase Returns
            ['module' => 'procurement', 'resource' => 'returns', 'action' => 'view', 'name' => 'purchase.return.view'],
            ['module' => 'procurement', 'resource' => 'returns', 'action' => 'create', 'name' => 'purchase.return.create'],
            ['module' => 'procurement', 'resource' => 'returns', 'action' => 'update', 'name' => 'purchase.return.update'],
            ['module' => 'procurement', 'resource' => 'returns', 'action' => 'delete', 'name' => 'purchase.return.delete'],

            // Plans, Coupons & Subscriptions
            ['module' => 'billing', 'resource' => 'plans', 'action' => 'view', 'name' => 'plans.view'],
            ['module' => 'billing', 'resource' => 'plans', 'action' => 'create', 'name' => 'plans.create'],
            ['module' => 'billing', 'resource' => 'plans', 'action' => 'update', 'name' => 'plans.update'],
            ['module' => 'billing', 'resource' => 'plans', 'action' => 'delete', 'name' => 'plans.delete'],

            ['module' => 'billing', 'resource' => 'coupons', 'action' => 'view', 'name' => 'coupons.view'],
            ['module' => 'billing', 'resource' => 'coupons', 'action' => 'create', 'name' => 'coupons.create'],
            ['module' => 'billing', 'resource' => 'coupons', 'action' => 'update', 'name' => 'coupons.update'],
            ['module' => 'billing', 'resource' => 'coupons', 'action' => 'delete', 'name' => 'coupons.delete'],

            ['module' => 'billing', 'resource' => 'subscriptions', 'action' => 'view', 'name' => 'subscriptions.view'],
            ['module' => 'billing', 'resource' => 'subscriptions', 'action' => 'cancel', 'name' => 'subscriptions.cancel'],

            // Bank Transfers
            ['module' => 'billing', 'resource' => 'bank_transfer', 'action' => 'view', 'name' => 'bank-transfer.view'],
            ['module' => 'billing', 'resource' => 'bank_transfer', 'action' => 'manage', 'name' => 'bank-transfer.manage'],

            // Helpdesk
            ['module' => 'helpdesk', 'resource' => 'tickets', 'action' => 'view', 'name' => 'helpdesk.view'],
            ['module' => 'helpdesk', 'resource' => 'tickets', 'action' => 'create', 'name' => 'helpdesk.create'],
            ['module' => 'helpdesk', 'resource' => 'tickets', 'action' => 'update', 'name' => 'helpdesk.update'],
            ['module' => 'helpdesk', 'resource' => 'tickets', 'action' => 'delete', 'name' => 'helpdesk.delete'],

            // Media
            ['module' => 'media', 'resource' => 'media', 'action' => 'view', 'name' => 'media.view'],
            ['module' => 'media', 'resource' => 'media', 'action' => 'create', 'name' => 'media.create'],
            ['module' => 'media', 'resource' => 'media', 'action' => 'delete', 'name' => 'media.delete'],

            // Messenger & Chat
            ['module' => 'chat', 'resource' => 'chat', 'action' => 'view', 'name' => 'chat.view'],
            ['module' => 'chat', 'resource' => 'chat', 'action' => 'send', 'name' => 'chat.send'],

            // AI Agent
            ['module' => 'ai', 'resource' => 'ai_agent', 'action' => 'view', 'name' => 'ai-agent.view'],
            ['module' => 'ai', 'resource' => 'ai_agent', 'action' => 'chat', 'name' => 'ai-agent.chat'],

            // System & Settings
            ['module' => 'modules', 'resource' => 'modules', 'action' => 'manage', 'name' => 'modules.manage'],
            ['module' => 'settings', 'resource' => 'settings', 'action' => 'view', 'name' => 'settings.view'],
            ['module' => 'settings', 'resource' => 'settings', 'action' => 'update', 'name' => 'settings.update'],

            // Module Runtimes
            ['module' => 'productservice', 'resource' => 'catalog', 'action' => 'manage', 'name' => 'product_service.manage'],
            ['module' => 'productservice', 'resource' => 'inventory', 'action' => 'adjust', 'name' => 'inventory.adjust'],
            ['module' => 'productservice', 'resource' => 'inventory', 'action' => 'manage', 'name' => 'inventory.manage'],
            ['module' => 'sales', 'resource' => 'sales', 'action' => 'manage', 'name' => 'sales.manage'],
            ['module' => 'procurement', 'resource' => 'purchases', 'action' => 'manage', 'name' => 'procurement.manage'],
            ['module' => 'account', 'resource' => 'ledger', 'action' => 'view', 'name' => 'account.view'],
            ['module' => 'account', 'resource' => 'ledger', 'action' => 'manage', 'name' => 'account.manage'],
            ['module' => 'hrm', 'resource' => 'hr', 'action' => 'view', 'name' => 'hrm.view'],
            ['module' => 'hrm', 'resource' => 'hr', 'action' => 'manage', 'name' => 'hrm.manage'],
            ['module' => 'lead', 'resource' => 'crm', 'action' => 'view', 'name' => 'crm.view'],
            ['module' => 'lead', 'resource' => 'crm', 'action' => 'manage', 'name' => 'crm.manage'],
            ['module' => 'taskly', 'resource' => 'projects', 'action' => 'view', 'name' => 'taskly.view'],
            ['module' => 'taskly', 'resource' => 'projects', 'action' => 'manage', 'name' => 'taskly.manage'],
            ['module' => 'pos', 'resource' => 'registers', 'action' => 'manage', 'name' => 'pos.manage'],
            ['module' => 'landingpage', 'resource' => 'landing', 'action' => 'manage', 'name' => 'landing.manage'],
            ['module' => 'core', 'resource' => 'webhooks', 'action' => 'manage', 'name' => 'webhooks.manage'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // Seed System Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'workspace-admin', 'organization_id' => null],
            ['display_name' => 'Workspace Admin', 'is_system' => true]
        );

        $memberRole = Role::firstOrCreate(
            ['name' => 'workspace-member', 'organization_id' => null],
            ['display_name' => 'Workspace Member', 'is_system' => true]
        );

        // Assign all permissions to Workspace Admin
        $allPermissionIds = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($allPermissionIds);

        // Assign basic view & switch permissions to Workspace Member
        $memberPermissionIds = Permission::whereIn('name', [
            'workspace.view',
            'workspace.switch',
            'workspace.members.view',
            'sales.invoice.view',
            'warehouses.view',
            'helpdesk.view',
            'media.view',
            'chat.view',
            'ai-agent.view',
        ])->pluck('id')->toArray();
        $memberRole->permissions()->sync($memberPermissionIds);
    }
}
