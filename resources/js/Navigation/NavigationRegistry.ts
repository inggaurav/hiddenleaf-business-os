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
  Boxes,
  BarChart3,
  UserCheck,
  CheckSquare,
  Store,
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  Clock3,
  ClipboardList,
  Contact,
  Gauge,
  Landmark,
  ListChecks,
  NotebookTabs,
  Receipt,
  SlidersHorizontal,
  UserRoundCog,
  WalletCards,
  Workflow,
} from 'lucide-react';

export interface NavigationItem {
  id: string;
  name: string;
  href?: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
  module?: string;
  roles?: string[];
  superAdminOnly?: boolean;
  description?: string;
  category?: string;
  children?: NavigationItem[];
}

export interface NavigationGroup {
  id: string;
  title: string;
  items: NavigationItem[];
}

const child = (
  id: string,
  name: string,
  href: string,
  icon: NavigationItem['icon'],
  options: Partial<NavigationItem> = {}
): NavigationItem => ({ id, name, href, icon, ...options });

export const ALL_NAVIGATION_GROUPS: NavigationGroup[] = [
  {
    id: 'overview',
    title: 'Overview',
    items: [
      child('nav-dashboard', 'Dashboard', '/dashboard', LayoutDashboard, {
        category: 'Overview',
        description: 'Workspace executive dashboard and operating summary',
      }),
    ],
  },
  {
    id: 'business',
    title: 'Business Modules',
    items: [
      {
        id: 'nav-crm',
        name: 'CRM',
        icon: UserCheck,
        permission: 'crm.view',
        module: 'lead',
        category: 'CRM',
        children: [
          child('nav-crm-dashboard', 'Dashboard', '/crm/dashboard', LayoutDashboard, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-leads', 'Leads', '/crm/leads', Contact, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-deals', 'Deals', '/crm/deals', BriefcaseBusiness, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-pipelines', 'Pipelines', '/crm/pipelines', Workflow, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-webforms', 'Web Forms', '/crm/webforms', FileSpreadsheet, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-activities', 'Activities', '/crm/activities', ClipboardList, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
          child('nav-crm-notes', 'Notes', '/crm/notes', NotebookTabs, { permission: 'crm.view', module: 'lead', category: 'CRM' }),
        ],
      },
      {
        id: 'nav-hrm',
        name: 'HRM',
        icon: Users,
        permission: 'hrm.view',
        module: 'hrm',
        category: 'HRM',
        children: [
          child('nav-hrm-dashboard', 'Dashboard', '/hrm/dashboard', LayoutDashboard, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-employees', 'Employees', '/hrm/employees', Users, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-branches', 'Branches', '/hrm/branches', Building2, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-departments', 'Departments', '/hrm/departments', Layers, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-designations', 'Designations', '/hrm/designations', UserRoundCog, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-shifts', 'Shifts', '/hrm/shifts', Clock3, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-attendance', 'Attendance', '/hrm/attendance', CalendarDays, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-leave', 'Leave Requests', '/hrm/leave-requests', ClipboardList, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-leave-types', 'Leave Types', '/hrm/leave-types', ListChecks, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-payroll', 'Payroll & Payslips', '/hrm/payroll', WalletCards, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-salary-components', 'Salary Components', '/hrm/salary-components', DollarSign, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-appraisals', 'Appraisals', '/hrm/appraisals', BarChart3, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-documents', 'Employee Documents', '/hrm/documents', FolderOpen, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
          child('nav-hrm-holidays', 'Holidays', '/hrm/holidays', CalendarDays, { permission: 'hrm.view', module: 'hrm', category: 'HRM' }),
        ],
      },
      {
        id: 'nav-sales',
        name: 'Sales',
        icon: Receipt,
        permission: 'sales.manage',
        module: 'sales',
        category: 'Sales',
        children: [
          child('nav-sales-dashboard', 'Dashboard', '/sales/dashboard', LayoutDashboard, { permission: 'sales.manage', module: 'sales', category: 'Sales' }),
          child('nav-sales-invoices', 'Invoices', '/sales-invoices', FileText, { permission: 'sales.manage', module: 'sales', category: 'Sales' }),
          child('nav-sales-proposals', 'Proposals', '/sales-proposals', FileSpreadsheet, { permission: 'sales.manage', module: 'sales', category: 'Sales' }),
          child('nav-sales-returns', 'Returns', '/sales-returns', RotateCcw, { permission: 'sales.manage', module: 'sales', category: 'Sales' }),
        ],
      },
      {
        id: 'nav-procurement',
        name: 'Procurement',
        icon: Truck,
        permission: 'procurement.manage',
        module: 'procurement',
        category: 'Procurement',
        children: [
          child('nav-procurement-dashboard', 'Dashboard', '/procurement/dashboard', LayoutDashboard, { permission: 'procurement.manage', module: 'procurement', category: 'Procurement' }),
          child('nav-purchase-invoices', 'Purchase Invoices', '/purchase-invoices', Truck, { permission: 'procurement.manage', module: 'procurement', category: 'Procurement' }),
          child('nav-purchase-returns', 'Purchase Returns', '/purchase-returns', RotateCcw, { permission: 'procurement.manage', module: 'procurement', category: 'Procurement' }),
        ],
      },
      {
        id: 'nav-inventory',
        name: 'Inventory',
        icon: Boxes,
        permission: 'product_service.manage',
        module: 'productservice',
        category: 'Inventory',
        children: [
          child('nav-inventory-dashboard', 'Dashboard', '/inventory/dashboard', LayoutDashboard, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-service', 'Products & Services', '/product-service', Package, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-stock', 'Stock', '/product-service/stock', Boxes, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-movements', 'Stock Movements', '/inventory/movements', ArrowLeftRight, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-categories', 'Categories', '/product-service/categories', Layers, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-units', 'Units', '/product-service/units', SlidersHorizontal, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-product-taxes', 'Taxes', '/product-service/taxes', Receipt, { permission: 'product_service.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-warehouses', 'Warehouses', '/warehouses', Warehouse, { permission: 'inventory.manage', module: 'productservice', category: 'Inventory' }),
          child('nav-transfers', 'Transfers', '/transfers', ArrowLeftRight, { permission: 'inventory.manage', module: 'productservice', category: 'Inventory' }),
        ],
      },
      {
        id: 'nav-accounting',
        name: 'Accounting',
        icon: Landmark,
        permission: 'account.view',
        module: 'account',
        category: 'Finance',
        children: [
          child('nav-accounting-dashboard', 'Dashboard', '/accounting/dashboard', LayoutDashboard, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-accounts', 'Chart of Accounts', '/accounting/accounts', BookOpen, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-journals', 'Journals', '/accounting/journals', FileText, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-customers', 'Customers', '/accounting/customers', Contact, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-vendors', 'Vendors', '/accounting/vendors', Building2, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-customer-payments', 'Customer Payments', '/accounting/customer-payments', WalletCards, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-vendor-payments', 'Vendor Payments', '/accounting/vendor-payments', WalletCards, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-revenues', 'Revenues', '/accounting/revenues', DollarSign, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-expenses', 'Expenses', '/accounting/expenses', Receipt, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-credit-notes', 'Credit Notes', '/accounting/credit-notes', FileText, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-debit-notes', 'Debit Notes', '/accounting/debit-notes', FileText, { permission: 'account.view', module: 'account', category: 'Finance' }),
          child('nav-accounting-reports', 'Reports', '/accounting/reports', BarChart3, { permission: 'account.view', module: 'account', category: 'Finance' }),
        ],
      },
      {
        id: 'nav-taskly',
        name: 'Projects & Tasks',
        icon: CheckSquare,
        permission: 'taskly.view',
        module: 'taskly',
        category: 'Projects',
        children: [
          child('nav-taskly-dashboard', 'Dashboard', '/taskly/dashboard', LayoutDashboard, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
          child('nav-taskly-projects', 'Projects', '/taskly/projects', BriefcaseBusiness, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
          child('nav-taskly-tasks', 'Tasks', '/taskly/tasks', CheckSquare, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
          child('nav-taskly-milestones', 'Milestones', '/taskly/milestones', Gauge, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
          child('nav-taskly-timesheets', 'Timesheets', '/taskly/timesheets', Clock3, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
          child('nav-taskly-issues', 'Issues', '/taskly/issues', ClipboardList, { permission: 'taskly.view', module: 'taskly', category: 'Projects' }),
        ],
      },
      {
        id: 'nav-pos',
        name: 'Point of Sale',
        icon: Store,
        permission: 'pos.manage',
        module: 'pos',
        category: 'POS',
        children: [
          child('nav-pos-dashboard', 'Dashboard', '/pos/dashboard', LayoutDashboard, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-terminal', 'Terminal', '/pos/terminal', Store, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-orders', 'Orders', '/pos/orders', ShoppingCart, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-counters', 'Billing Counters', '/pos/billing-counters', Layers, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-discounts', 'Discounts', '/pos/discounts', Tag, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-sales-report', 'Sales Report', '/pos/reports/sales', BarChart3, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-product-report', 'Product Report', '/pos/reports/products', BarChart3, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-customer-report', 'Customer Report', '/pos/reports/customers', BarChart3, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
          child('nav-pos-returns', 'Returns', '/pos/returns', RotateCcw, { permission: 'pos.manage', module: 'pos', category: 'POS' }),
        ],
      },
    ],
  },
  {
    id: 'operations',
    title: 'Operations & Collaboration',
    items: [
      child('nav-helpdesk', 'Helpdesk', '/helpdesk-tickets', Headphones, { permission: 'workspace.view', category: 'Operations' }),
      child('nav-media', 'Media Library', '/media/page', FolderOpen, { permission: 'workspace.view', category: 'Storage' }),
      child('nav-messenger', 'Live Chat', '/chats', MessageSquare, { permission: 'workspace.view', category: 'Communication' }),
      child('nav-ai-agent', 'Mr Fox AI Assistant', '/ai-agent/chat', Bot, { permission: 'workspace.view', category: 'Intelligence' }),
      child('nav-workspaces', 'Workspaces', '/workspaces', Layers, { permission: 'workspace.view', category: 'Workspace' }),
    ],
  },
  {
    id: 'billing',
    title: 'Plans & Billing',
    items: [
      child('nav-plans', 'Subscription Plans', '/plans', CreditCard, { roles: ['company', 'company_admin'], category: 'Billing' }),
      child('nav-orders', 'Orders', '/orders', ShoppingCart, { roles: ['company', 'company_admin'], category: 'Billing' }),
      child('nav-subscriptions', 'Subscriptions', '/subscriptions', Repeat, { roles: ['company', 'company_admin'], category: 'Billing' }),
      child('nav-bank-transfers', 'Bank Transfers', '/bank-transfer', DollarSign, { roles: ['company', 'company_admin'], category: 'Billing' }),
    ],
  },
  {
    id: 'workspace-admin',
    title: 'Workspace Administration',
    items: [
      child('nav-roles', 'Roles & Permissions', '/roles', Shield, { permission: 'roles.view', category: 'Security' }),
      child('nav-users', 'Users & Team', '/users', Users, { permission: 'workspace.members.view', category: 'Security' }),
      child('nav-modules', 'Module Catalog', '/modules', Cpu, { permission: 'modules.manage', category: 'System' }),
      child('nav-settings', 'Settings', '/settings', Settings, { permission: 'modules.manage', category: 'System' }),
    ],
  },
  {
    id: 'platform-admin',
    title: 'Platform Administration',
    items: [
      {
        id: 'nav-platform-admin',
        name: 'Super Admin',
        icon: Shield,
        superAdminOnly: true,
        children: [
          child('nav-super-dashboard', 'Platform Dashboard', '/super-admin/dashboard', LayoutDashboard, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-users', 'Companies & Users', '/users', Users, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-plans', 'Plans', '/plans', CreditCard, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-orders', 'Orders', '/orders', ShoppingCart, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-subscriptions', 'Subscriptions', '/subscriptions', Repeat, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-coupons', 'Coupons', '/coupons', Tag, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-modules', 'Modules & Add-ons', '/modules', Cpu, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-bank-transfers', 'Bank Transfers', '/bank-transfer', DollarSign, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-settings', 'System Settings', '/super-admin/settings', Settings, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-translations', 'Translations', '/super-admin/translations', BookOpen, { superAdminOnly: true, category: 'Platform' }),
        ],
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
  if (isSuperAdmin) return true;
  if (item.superAdminOnly) return false;
  if (item.module && !enabledModules.includes(item.module)) return false;
  if (item.roles?.length && !item.roles.includes(user?.role)) return false;
  if (user?.role === 'company_admin' || user?.role === 'company') return true;
  if (item.permission && !userPermissions.includes(item.permission)) return false;
  return true;
}

function filterItem(
  item: NavigationItem,
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[],
  enabledModules: string[]
): NavigationItem | null {
  if (!isItemAuthorized(item, user, isSuperAdmin, userPermissions, enabledModules)) return null;

  if (!item.children?.length) return item;

  const children = item.children
    .map((entry) => filterItem(entry, user, isSuperAdmin, userPermissions, enabledModules))
    .filter(Boolean) as NavigationItem[];

  if (children.length === 0 && !item.href) return null;
  return { ...item, children };
}

export function filterNavigation(
  groups: NavigationGroup[],
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[],
  enabledModules: string[] = []
): NavigationGroup[] {
  return groups
    .map((group) => ({
      ...group,
      items: group.items
        .map((item) => filterItem(item, user, isSuperAdmin, userPermissions, enabledModules))
        .filter(Boolean) as NavigationItem[],
    }))
    .filter((group) => group.items.length > 0);
}

export function flattenNavigation(items: NavigationItem[]): NavigationItem[] {
  return items.flatMap((item) => [
    ...(item.href ? [{ ...item, children: undefined }] : []),
    ...(item.children ? flattenNavigation(item.children) : []),
  ]);
}
