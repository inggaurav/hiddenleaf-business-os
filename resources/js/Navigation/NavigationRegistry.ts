import React from 'react';
import {
  LayoutDashboard,
  FileText,
  FileSpreadsheet,
  RotateCcw,
  ShoppingCart,
  Warehouse,
  ArrowLeftRight,
  Truck,
  CreditCard,
  Tag,
  Repeat,
  DollarSign,
  Headphones,
  FolderOpen,
  MessageSquare,
  Bot,
  Layers,
  Shield,
  Users,
  Cpu,
  Settings,
  BookOpen,
  Package,
} from 'lucide-react';

export interface NavigationItem {
  id: string;
  name: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
  module?: string;
  roles?: string[];
  superAdminOnly?: boolean;
  description?: string;
  category?: string;
}

export interface NavigationGroup {
  id: string;
  title: string;
  items: NavigationItem[];
}

export const ALL_NAVIGATION_GROUPS: NavigationGroup[] = [
  {
    id: 'core',
    title: 'Core & Overview',
    items: [
      {
        id: 'nav-dashboard',
        name: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
        category: 'Overview',
        description: 'Tenant executive metrics and workspace overview',
      },
    ],
  },
  {
    id: 'sales',
    title: 'Sales & Invoicing',
    items: [
      {
        id: 'nav-sales-invoices',
        name: 'Sales Invoices',
        href: '/sales-invoices',
        icon: FileText,
        permission: 'sales.manage',
        category: 'Sales',
        description: 'Customer invoices, line items, and payment tracking',
      },
      {
        id: 'nav-sales-proposals',
        name: 'Sales Proposals',
        href: '/sales-proposals',
        icon: FileSpreadsheet,
        permission: 'sales.manage',
        category: 'Sales',
        description: 'Estimates, quotations, and proposal conversion',
      },
      {
        id: 'nav-sales-returns',
        name: 'Sales Returns',
        href: '/sales-returns',
        icon: RotateCcw,
        permission: 'sales.manage',
        category: 'Sales',
        description: 'Credit notes and customer return authorizations',
      },
      {
        id: 'nav-orders',
        name: 'Plan Orders',
        href: '/orders',
        icon: ShoppingCart,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        description: 'Subscription order history and plan receipts',
      },
    ],
  },
  {
    id: 'procurement',
    title: 'Procurement & Inventory',
    items: [
      {
        id: 'nav-product-service',
        name: 'Products & Services',
        href: '/product-service',
        icon: Package,
        permission: 'product_service.manage',
        module: 'productservice',
        category: 'Inventory',
        description: 'Item catalog, categories, units, and inventory pricing',
      },
      {
        id: 'nav-warehouses',
        name: 'Warehouses',
        href: '/warehouses',
        icon: Warehouse,
        permission: 'inventory.manage',
        module: 'productservice',
        category: 'Inventory',
        description: 'Storage locations, inventory tracking, and stock levels',
      },
      {
        id: 'nav-transfers',
        name: 'Transfers',
        href: '/transfers',
        icon: ArrowLeftRight,
        permission: 'inventory.manage',
        module: 'productservice',
        category: 'Inventory',
        description: 'Inter-warehouse inventory stock transfers',
      },
      {
        id: 'nav-purchase-invoices',
        name: 'Purchase Invoices',
        href: '/purchase-invoices',
        icon: Truck,
        permission: 'procurement.manage',
        category: 'Procurement',
        description: 'Vendor bills and supplier purchasing accounts',
      },
      {
        id: 'nav-purchase-returns',
        name: 'Purchase Returns',
        href: '/purchase-returns',
        icon: RotateCcw,
        permission: 'procurement.manage',
        category: 'Procurement',
        description: 'Debit notes and vendor return adjustments',
      },
    ],
  },
  {
    id: 'accounting',
    title: 'Finance & Accounting',
    items: [
      {
        id: 'nav-accounting-accounts',
        name: 'Chart of Accounts',
        href: '/accounting/accounts',
        icon: BookOpen,
        permission: 'account.view',
        module: 'account',
        category: 'Finance',
        description: 'General ledger, account hierarchy, and journal entries',
      },
    ],
  },
  {
    id: 'billing',
    title: 'Plans & Subscriptions',
    items: [
      {
        id: 'nav-plans',
        name: 'Subscription Plans',
        href: '/plans',
        icon: CreditCard,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        description: 'Tiered plan catalog and pricing tiers',
      },
      {
        id: 'nav-coupons',
        name: 'Coupons',
        href: '/coupons',
        icon: Tag,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        description: 'Discount codes and promotional campaigns',
      },
      {
        id: 'nav-subscriptions',
        name: 'Active Subscriptions',
        href: '/subscriptions',
        icon: Repeat,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        description: 'Tenant active licenses and recurrence cycles',
      },
      {
        id: 'nav-bank-transfers',
        name: 'Bank Transfers',
        href: '/bank-transfer',
        icon: DollarSign,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        description: 'Offline wire payment review and reconciliation',
      },
    ],
  },
  {
    id: 'operations',
    title: 'Operations & Workspace',
    items: [
      {
        id: 'nav-helpdesk',
        name: 'Helpdesk & Support',
        href: '/helpdesk-tickets',
        icon: Headphones,
        permission: 'workspace.view',
        category: 'Operations',
        description: 'Customer ticket dispatch and issue resolution',
      },
      {
        id: 'nav-media',
        name: 'Media Library',
        href: '/media/page',
        icon: FolderOpen,
        permission: 'workspace.view',
        category: 'Storage',
        description: 'S3 asset management and shared file storage',
      },
      {
        id: 'nav-messenger',
        name: 'Live Chat',
        href: '/chats',
        icon: MessageSquare,
        permission: 'workspace.view',
        category: 'Communication',
        description: 'Internal team messaging and customer communications',
      },
      {
        id: 'nav-ai-agent',
        name: 'Mr Fox AI Assistant',
        href: '/ai-agent/chat',
        icon: Bot,
        permission: 'workspace.view',
        category: 'Intelligence',
        description: 'Neural reasoning copilot and workspace insights',
      },
      {
        id: 'nav-workspaces',
        name: 'Workspaces',
        href: '/workspaces',
        icon: Layers,
        permission: 'workspace.view',
        category: 'Workspace',
        description: 'Tenant workspace configuration and isolation settings',
      },
    ],
  },
  {
    id: 'admin',
    title: 'Access & System',
    items: [
      {
        id: 'nav-roles',
        name: 'Roles & Permissions',
        href: '/roles',
        icon: Shield,
        permission: 'roles.view',
        category: 'Security',
        description: 'RBAC role definitions and granular capabilities',
      },
      {
        id: 'nav-users',
        name: 'User Management',
        href: '/users',
        icon: Users,
        permission: 'workspace.members.view',
        category: 'Security',
        description: 'Team members, user invitations, and access status',
      },
      {
        id: 'nav-modules',
        name: 'Module Catalog',
        href: '/modules',
        icon: Cpu,
        permission: 'modules.manage',
        category: 'System',
        description: 'Modular feature discovery, activation, and licenses',
      },
      {
        id: 'nav-settings',
        name: 'System Settings',
        href: '/settings',
        icon: Settings,
        permission: 'modules.manage',
        category: 'System',
        description: 'Branding, mail, webhooks, and security preferences',
      },
    ],
  },
];

export function isItemAuthorized(
  item: NavigationItem,
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[],
  enabledModules: string[] = []
): boolean {
  if (isSuperAdmin) {
    return true;
  }

  if (item.superAdminOnly) {
    return false;
  }

  // Module check takes precedence
  if (item.module && !enabledModules.includes(item.module)) {
    return false;
  }

  // Company admins and owner role have broad access for enabled modules
  if (user?.role === 'company_admin' || user?.role === 'company') {
    return true;
  }

  // Role check
  if (item.roles && item.roles.length > 0) {
    if (!item.roles.includes(user?.role)) {
      return false;
    }
  }

  // Permission check
  if (item.permission) {
    if (!userPermissions.includes(item.permission)) {
      return false;
    }
  }

  return true;
}

export function filterNavigation(
  groups: NavigationGroup[],
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[],
  enabledModules: string[] = []
): NavigationGroup[] {
  return groups
    .map((grp) => ({
      ...grp,
      items: grp.items.filter((item) =>
        isItemAuthorized(item, user, isSuperAdmin, userPermissions, enabledModules)
      ),
    }))
    .filter((grp) => grp.items.length > 0);
}
