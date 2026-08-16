import React from 'react';
import {
  Activity,
  ArrowLeftRight,
  BarChart3,
  Bell,
  BookOpen,
  Bot,
  Boxes,
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  CheckSquare,
  ClipboardCheck,
  ClipboardList,
  Clock3,
  Contact,
  Cpu,
  CreditCard,
  DollarSign,
  FileSpreadsheet,
  FileText,
  FolderOpen,
  Gauge,
  Globe2,
  Headphones,
  Inbox,
  KeyRound,
  Landmark,
  Languages,
  Layers,
  LayoutDashboard,
  ListChecks,
  Mail,
  MessageSquare,
  NotebookTabs,
  Package,
  Receipt,
  Repeat,
  RotateCcw,
  Settings,
  Shield,
  ShoppingCart,
  SlidersHorizontal,
  Sparkles,
  Store,
  Tag,
  Truck,
  UserCheck,
  UserRoundCog,
  Users,
  WalletCards,
  Warehouse,
  Webhook,
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

const moduleChild = (
  id: string,
  name: string,
  href: string,
  icon: NavigationItem['icon'],
  module: string,
  permission: string,
  category: string
): NavigationItem => child(id, name, href, icon, { module, permission, category });

export const ALL_NAVIGATION_GROUPS: NavigationGroup[] = [
  {
    id: 'portal',
    title: 'Customer & Vendor Portal',
    items: [
      child('nav-portal-dashboard', 'Portal Dashboard', '/portal/dashboard', LayoutDashboard, {
        roles: ['client', 'customer', 'vendor'],
        category: 'Portal',
        description: 'Own invoices, proposals, payments, returns and assigned projects',
      }),
    ],
  },
  {
    id: 'overview',
    title: 'Overview',
    items: [
      child('nav-dashboard', 'Dashboard', '/dashboard', LayoutDashboard, {
        category: 'Overview',
        description: 'Workspace executive metrics and business overview',
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
          moduleChild('nav-crm-dashboard', 'Dashboard', '/crm/dashboard', LayoutDashboard, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-leads', 'Leads', '/crm/leads', Contact, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-deals', 'Deals', '/crm/deals', BriefcaseBusiness, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-pipelines', 'Pipelines', '/crm/pipelines', Workflow, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-webforms', 'Web Forms', '/crm/webforms', FileSpreadsheet, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-activities', 'Activities', '/crm/activities', ClipboardList, 'lead', 'crm.view', 'CRM'),
          moduleChild('nav-crm-notes', 'Notes', '/crm/notes', NotebookTabs, 'lead', 'crm.view', 'CRM'),
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
          moduleChild('nav-hrm-dashboard', 'Dashboard', '/hrm/dashboard', LayoutDashboard, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-employees', 'Employees', '/hrm/employees', Users, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-branches', 'Branches', '/hrm/branches', Building2, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-departments', 'Departments', '/hrm/departments', Layers, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-designations', 'Designations', '/hrm/designations', UserRoundCog, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-shifts', 'Shifts', '/hrm/shifts', Clock3, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-attendance', 'Attendance', '/hrm/attendance', CalendarDays, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-leave', 'Leave Requests', '/hrm/leave-requests', ClipboardList, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-leave-types', 'Leave Types', '/hrm/leave-types', ListChecks, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-payroll', 'Payroll & Payslips', '/hrm/payroll', WalletCards, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-set-salary', 'Set Salary', '/hrm/set-salary', DollarSign, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-salary-components', 'Salary Components', '/hrm/salary-components', DollarSign, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-leave-balances', 'Leave Balance', '/hrm/leave-balances', ListChecks, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-appraisals', 'Appraisals', '/hrm/appraisals', BarChart3, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-documents', 'Employee Documents', '/hrm/documents', FolderOpen, 'hrm', 'hrm.manage', 'HRM'),
          moduleChild('nav-hrm-holidays', 'Holidays', '/hrm/holidays', CalendarDays, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-awards', 'Awards', '/hrm/lifecycle/award', Sparkles, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-promotions', 'Promotions', '/hrm/lifecycle/promotion', UserCheck, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-resignations', 'Resignations', '/hrm/lifecycle/resignation', FileText, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-terminations', 'Terminations', '/hrm/lifecycle/termination', UserRoundCog, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-warnings', 'Warnings', '/hrm/lifecycle/warning', Bell, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-complaints', 'Complaints', '/hrm/lifecycle/complaint', MessageSquare, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-transfers', 'Employee Transfers', '/hrm/lifecycle/transfer', ArrowLeftRight, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-acknowledgements', 'Acknowledgements', '/hrm/lifecycle/acknowledgement', CheckSquare, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-events', 'Events', '/hrm/lifecycle/event', CalendarDays, 'hrm', 'hrm.view', 'HRM'),
          moduleChild('nav-hrm-communications', 'Announcements & Policies', '/hrm/communications', Inbox, 'hrm', 'hrm.view', 'HRM'),
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
          moduleChild('nav-sales-dashboard', 'Dashboard', '/sales/dashboard', LayoutDashboard, 'sales', 'sales.manage', 'Sales'),
          moduleChild('nav-sales-invoices', 'Invoices', '/sales-invoices', FileText, 'sales', 'sales.manage', 'Sales'),
          moduleChild('nav-sales-proposals', 'Proposals', '/sales-proposals', FileSpreadsheet, 'sales', 'sales.manage', 'Sales'),
          moduleChild('nav-sales-returns', 'Returns', '/sales-returns', RotateCcw, 'sales', 'sales.manage', 'Sales'),
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
          moduleChild('nav-procurement-dashboard', 'Dashboard', '/procurement/dashboard', LayoutDashboard, 'procurement', 'procurement.manage', 'Procurement'),
          moduleChild('nav-purchase-invoices', 'Purchase Invoices', '/purchase-invoices', Truck, 'procurement', 'procurement.manage', 'Procurement'),
          moduleChild('nav-purchase-returns', 'Purchase Returns', '/purchase-returns', RotateCcw, 'procurement', 'procurement.manage', 'Procurement'),
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
          moduleChild('nav-inventory-dashboard', 'Dashboard', '/inventory/dashboard', LayoutDashboard, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-service', 'Products & Services', '/product-service', Package, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-stock', 'Stock', '/product-service/stock', Boxes, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-movements', 'Stock Movements', '/inventory/movements', ArrowLeftRight, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-categories', 'Categories', '/product-service/categories', Layers, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-units', 'Units', '/product-service/units', SlidersHorizontal, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-product-taxes', 'Taxes', '/product-service/taxes', Receipt, 'productservice', 'product_service.manage', 'Inventory'),
          moduleChild('nav-warehouses', 'Warehouses', '/warehouses', Warehouse, 'productservice', 'inventory.manage', 'Inventory'),
          moduleChild('nav-transfers', 'Transfers', '/transfers', ArrowLeftRight, 'productservice', 'inventory.manage', 'Inventory'),
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
          moduleChild('nav-accounting-dashboard', 'Dashboard', '/accounting/dashboard', LayoutDashboard, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-bank-accounts', 'Bank Accounts', '/accounting/bank-accounts', Landmark, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-bank-transactions', 'Bank Transactions', '/accounting/bank-transactions', ArrowLeftRight, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-bank-transfers', 'Bank Transfers', '/accounting/bank-transfers', Repeat, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-accounts', 'Chart of Accounts', '/accounting/accounts', BookOpen, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-journals', 'Journals', '/accounting/journals', FileText, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-customers', 'Customers', '/accounting/customers', Contact, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-vendors', 'Vendors', '/accounting/vendors', Building2, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-customer-payments', 'Customer Payments', '/accounting/customer-payments', WalletCards, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-vendor-payments', 'Vendor Payments', '/accounting/vendor-payments', WalletCards, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-revenues', 'Revenues', '/accounting/revenues', DollarSign, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-expenses', 'Expenses', '/accounting/expenses', Receipt, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-credit-notes', 'Credit Notes', '/accounting/credit-notes', FileText, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-debit-notes', 'Debit Notes', '/accounting/debit-notes', FileText, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-reports', 'Reports', '/accounting/reports', BarChart3, 'account', 'account.view', 'Finance'),
          moduleChild('nav-accounting-types', 'System Setup', '/accounting/account-types', Settings, 'account', 'account.view', 'Finance'),
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
          moduleChild('nav-taskly-dashboard', 'Dashboard', '/taskly/dashboard', LayoutDashboard, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-projects', 'Projects', '/taskly/projects', BriefcaseBusiness, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-tasks', 'Tasks', '/taskly/tasks', CheckSquare, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-milestones', 'Milestones', '/taskly/milestones', Gauge, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-timesheets', 'Timesheets', '/taskly/timesheets', Clock3, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-issues', 'Issues', '/taskly/issues', ClipboardList, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-payments', 'Project Payments', '/taskly/project-payments', WalletCards, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-reports', 'Projects Report', '/taskly/reports', BarChart3, 'taskly', 'taskly.view', 'Projects'),
          moduleChild('nav-taskly-setup', 'System Setup', '/taskly/setup', Settings, 'taskly', 'taskly.view', 'Projects'),
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
          moduleChild('nav-pos-dashboard', 'Dashboard', '/pos/dashboard', LayoutDashboard, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-terminal', 'Terminal', '/pos/terminal', Store, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-barcode', 'Print Barcode', '/pos/barcode', Tag, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-orders', 'Orders', '/pos/orders', ShoppingCart, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-counters', 'Billing Counters', '/pos/billing-counters', Layers, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-discounts', 'Discounts', '/pos/discounts', Tag, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-sales-report', 'Sales Report', '/pos/reports/sales', BarChart3, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-product-report', 'Product Report', '/pos/reports/products', BarChart3, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-customer-report', 'Customer Report', '/pos/reports/customers', BarChart3, 'pos', 'pos.manage', 'POS'),
          moduleChild('nav-pos-returns', 'Returns', '/pos/returns', RotateCcw, 'pos', 'pos.manage', 'POS'),
        ],
      },
    ],
  },
  {
    id: 'operations',
    title: 'Operations & Collaboration',
    items: [
      {
        id: 'nav-communications',
        name: 'Communications',
        icon: Inbox,
        permission: 'workspace.view',
        category: 'Communication',
        children: [
          child('nav-unified-inbox', 'Unified Inbox', '/inbox', Inbox, { permission: 'workspace.view', module: 'communications', category: 'Communication' }),
          child('nav-live-chat', 'Live Chat', '/chats', MessageSquare, { permission: 'workspace.view', category: 'Communication' }),
        ],
      },
      {
        id: 'nav-helpdesk',
        name: 'Helpdesk',
        icon: Headphones,
        permission: 'workspace.view',
        category: 'Operations',
        children: [
          child('nav-helpdesk-tickets', 'Tickets', '/helpdesk-tickets', Headphones, { permission: 'workspace.view', category: 'Operations' }),
          child('nav-helpdesk-categories', 'Categories', '/helpdesk-categories', Layers, { permission: 'workspace.view', category: 'Operations' }),
        ],
      },
      {
        id: 'nav-intelligence',
        name: 'Intelligence & Automation',
        icon: Sparkles,
        permission: 'workspace.view',
        category: 'Intelligence',
        children: [
          child('nav-command-center', 'Command Center', '/command-center', Gauge, { permission: 'workspace.view', module: 'command_center', category: 'Intelligence' }),
          child('nav-approvals', 'Approval Center', '/command-center/approvals', ClipboardCheck, { permission: 'workspace.view', module: 'command_center', category: 'Intelligence' }),
          child('nav-automations', 'Automations', '/automations', Workflow, { permission: 'workspace.view', module: 'automations', category: 'Automation' }),
          child('nav-missions', 'Missions', '/missions', Activity, { permission: 'workspace.view', module: 'missions', category: 'Automation' }),
          child('nav-ai-agent', 'Mr Fox AI Assistant', '/ai-agent/chat', Bot, { permission: 'workspace.view', category: 'Intelligence' }),
        ],
      },
      child('nav-media', 'Media Library', '/media/page', FolderOpen, { permission: 'workspace.view', category: 'Storage' }),
      child('nav-cms', 'Landing Page CMS', '/landing', Globe2, { permission: 'landing.manage', module: 'landingpage', category: 'CMS' }),
    ],
  },
  {
    id: 'billing',
    title: 'Plans & Billing',
    items: [
      {
        id: 'nav-billing',
        name: 'Subscription & Billing',
        icon: CreditCard,
        roles: ['company', 'company_admin'],
        category: 'Billing',
        children: [
          child('nav-plans', 'Subscription Plans', '/plans', CreditCard, { roles: ['company', 'company_admin'], category: 'Billing' }),
          child('nav-orders', 'Orders', '/orders', ShoppingCart, { roles: ['company', 'company_admin'], category: 'Billing' }),
          child('nav-subscriptions', 'Subscriptions', '/subscriptions', Repeat, { roles: ['company', 'company_admin'], category: 'Billing' }),
          child('nav-bank-transfers', 'Bank Transfers', '/bank-transfer', DollarSign, { roles: ['company', 'company_admin'], category: 'Billing' }),
        ],
      },
    ],
  },
  {
    id: 'workspace-admin',
    title: 'Workspace Administration',
    items: [
      {
        id: 'nav-team-access',
        name: 'Team & Access',
        icon: Users,
        permission: 'workspace.view',
        category: 'Security',
        children: [
          child('nav-users', 'Users & Team', '/users', Users, { permission: 'workspace.members.view', category: 'Security' }),
          child('nav-roles', 'Roles & Permissions', '/roles', Shield, { permission: 'roles.view', category: 'Security' }),
          child('nav-workspaces', 'Workspaces', '/workspaces', Layers, { permission: 'workspace.view', category: 'Workspace' }),
          child('nav-onboarding', 'Business Setup', '/onboarding', ClipboardCheck, { permission: 'workspace.view', category: 'Workspace' }),
        ],
      },
      child('nav-modules', 'Modules & Add-ons', '/modules', Cpu, { permission: 'modules.manage', category: 'System' }),
      {
        id: 'nav-settings-parent',
        name: 'Settings',
        icon: Settings,
        permission: 'settings.view',
        category: 'System',
        children: [
          child('nav-settings', 'General Settings', '/settings', Settings, { permission: 'settings.view', category: 'System' }),
          child('nav-languages', 'Languages', '/languages', Languages, { permission: 'settings.localization.manage', category: 'Localization' }),
          child('nav-email-templates', 'Email Templates', '/settings/email-templates', Mail, { permission: 'settings.notifications.manage', category: 'Communication' }),
          child('nav-notification-templates', 'Notification Templates', '/notification-templates', Bell, { permission: 'settings.notifications.manage', category: 'Communication' }),
          child('nav-notifications', 'Notifications', '/notifications', Bell, { permission: 'workspace.view', category: 'Communication' }),
          child('nav-webhooks', 'Webhooks', '/webhooks', Webhook, { permission: 'webhooks.manage', category: 'Integration' }),
          child('nav-api-tokens', 'API Tokens', '/settings/api-tokens', KeyRound, { permission: 'settings.integrations.manage', category: 'Integration' }),
        ],
      },
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
          child('nav-super-companies', 'Companies', '/super-admin/companies', Building2, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-users', 'Users', '/users', Users, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-plans', 'Plans', '/plans', CreditCard, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-orders', 'Orders', '/orders', ShoppingCart, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-subscriptions', 'Subscriptions', '/subscriptions', Repeat, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-coupons', 'Coupons', '/coupons', Tag, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-modules', 'Modules & Add-ons', '/modules', Cpu, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-bank-transfers', 'Bank Transfers', '/bank-transfer', DollarSign, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-settings', 'System Settings', '/super-admin/settings', Settings, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-translations', 'Translations', '/super-admin/translations', Globe2, { superAdminOnly: true, category: 'Platform' }),
          child('nav-super-update', 'System Update', '/update', Cpu, { superAdminOnly: true, category: 'Platform' }),
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
  if (['client', 'customer', 'vendor'].includes(user?.role)) {
    groups = groups.filter((group) => group.id === 'portal');
  } else {
    groups = groups.filter((group) => group.id !== 'portal');
  }
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
