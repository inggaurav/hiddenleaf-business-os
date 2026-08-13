import React from 'react';
import { 
  LayoutDashboard, 
  Layers, 
  Users, 
  Settings, 
  CreditCard, 
  FileText, 
  DollarSign, 
  Headphones, 
  Image, 
  MessageSquare, 
  Building, 
  ShieldCheck, 
  Tag, 
  Globe, 
  Mail, 
  Sliders, 
  Bot,
  Package,
  Activity,
  ArrowRightLeft,
  Calendar
} from 'lucide-react';

export interface NavigationItem {
  id: string;
  name: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
  module?: string;
  superAdminOnly?: boolean;
  companyAdminOnly?: boolean;
  category?: 'Navigation' | 'Actions' | 'Mr Fox Intelligence';
}

export interface NavigationGroup {
  group: string;
  items: NavigationItem[];
}

export const ALL_NAVIGATION_GROUPS: NavigationGroup[] = [
  {
    group: 'Overview',
    items: [
      { id: 'nav-dashboard', name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, category: 'Navigation' },
    ],
  },
  {
    group: 'Sales & Invoicing',
    items: [
      { id: 'nav-sales-invoices', name: 'Sales Invoices', href: '/sales-invoices', icon: FileText, category: 'Navigation' },
      { id: 'nav-sales-proposals', name: 'Sales Proposals', href: '/sales-proposals', icon: FileText, category: 'Navigation' },
      { id: 'nav-sales-returns', name: 'Sales Returns', href: '/sales-returns', icon: ArrowRightLeft, category: 'Navigation' },
      { id: 'nav-orders', name: 'Orders & Billing', href: '/orders', icon: DollarSign, category: 'Navigation' },
    ],
  },
  {
    group: 'Operations & Inventory',
    items: [
      { id: 'nav-warehouses', name: 'Warehouses', href: '/warehouses', icon: Building, category: 'Navigation' },
      { id: 'nav-transfers', name: 'Stock Transfers', href: '/transfers', icon: ArrowRightLeft, category: 'Navigation' },
      { id: 'nav-purchase-invoices', name: 'Purchase Invoices', href: '/purchase-invoices', icon: FileText, category: 'Navigation' },
      { id: 'nav-purchase-returns', name: 'Purchase Returns', href: '/purchase-returns', icon: ArrowRightLeft, category: 'Navigation' },
    ],
  },
  {
    group: 'Finance & SaaS',
    items: [
      { id: 'nav-plans', name: 'Plans & Pricing', href: '/plans', icon: CreditCard, category: 'Navigation' },
      { id: 'nav-coupons', name: 'Promo Coupons', href: '/coupons', icon: Tag, category: 'Navigation' },
      { id: 'nav-subscriptions', name: 'Subscriptions', href: '/subscriptions', icon: Calendar, category: 'Navigation' },
      { id: 'nav-bank-transfer', name: 'Bank Transfers', href: '/bank-transfer', icon: DollarSign, category: 'Navigation' },
    ],
  },
  {
    group: 'Communications',
    items: [
      { id: 'nav-helpdesk', name: 'Helpdesk Tickets', href: '/helpdesk-tickets', icon: Headphones, category: 'Navigation' },
      { id: 'nav-media', name: 'Media Library', href: '/media/page', icon: Image, category: 'Navigation' },
      { id: 'nav-chat', name: 'Team Messenger', href: '/chats', icon: MessageSquare, category: 'Navigation' },
      { id: 'nav-ai-agent', name: 'AI Copilot', href: '/ai-agent/chat', icon: Bot, category: 'Navigation' },
    ],
  },
  {
    group: 'Administration',
    items: [
      { id: 'nav-workspaces', name: 'Workspaces', href: '/workspaces', icon: Layers, category: 'Navigation' },
      { id: 'nav-roles', name: 'Roles & RBAC', href: '/roles', icon: ShieldCheck, category: 'Navigation' },
      { id: 'nav-users', name: 'Team & Users', href: '/users', icon: Users, category: 'Navigation' },
      { id: 'nav-login-history', name: 'Login History', href: '/users-login-history', icon: Activity, category: 'Navigation' },
      { id: 'nav-modules', name: 'Module Catalog', href: '/modules', icon: Package, category: 'Navigation' },
      { id: 'nav-languages', name: 'Languages', href: '/languages', icon: Globe, category: 'Navigation' },
      { id: 'nav-email-templates', name: 'Email Templates', href: '/email-templates', icon: Mail, category: 'Navigation' },
      { id: 'nav-settings', name: 'Settings', href: '/settings', icon: Sliders, category: 'Navigation' },
      { id: 'nav-superadmin', name: 'Super Admin', href: '/super-admin/dashboard', icon: ShieldCheck, superAdminOnly: true, category: 'Navigation' },
    ],
  },
];

export function filterNavigation(
  groups: NavigationGroup[],
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[] = []
): NavigationGroup[] {
  return groups
    .map((grp) => ({
      group: grp.group,
      items: grp.items.filter((item) => {
        if (item.superAdminOnly && !isSuperAdmin) {
          return false;
        }
        if (item.permission && !isSuperAdmin && !userPermissions.includes(item.permission)) {
          return false;
        }
        return true;
      }),
    }))
    .filter((grp) => grp.items.length > 0);
}
