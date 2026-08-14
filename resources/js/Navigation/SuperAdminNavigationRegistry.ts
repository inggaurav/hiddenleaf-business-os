import {
  Bell,
  CreditCard,
  FolderOpen,
  Headphones,
  Landmark,
  LayoutDashboard,
  Mail,
  PackagePlus,
  Settings,
  ShoppingCart,
  Tag,
  Users,
} from 'lucide-react';
import type { NavigationGroup } from '@/Navigation/NavigationRegistry';

/**
 * Platform-only navigation matching the WorkDo Super Admin information
 * architecture. Tenant ERP modules intentionally do not appear here.
 */
export const SUPER_ADMIN_NAVIGATION_GROUPS: NavigationGroup[] = [
  {
    id: 'superadmin-overview',
    title: 'Platform',
    items: [
      {
        id: 'superadmin-dashboard',
        name: 'Dashboard',
        href: '/super-admin/dashboard',
        icon: LayoutDashboard,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-users',
        name: 'Users',
        href: '/users',
        icon: Users,
        superAdminOnly: true,
      },
    ],
  },
  {
    id: 'superadmin-helpdesk',
    title: 'Helpdesk',
    items: [
      {
        id: 'superadmin-tickets',
        name: 'Tickets',
        href: '/helpdesk-tickets',
        icon: Headphones,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-ticket-categories',
        name: 'Categories',
        href: '/helpdesk-categories',
        icon: Tag,
        superAdminOnly: true,
      },
    ],
  },
  {
    id: 'superadmin-templates',
    title: 'Templates',
    items: [
      {
        id: 'superadmin-email-templates',
        name: 'Email Templates',
        href: '/email-templates',
        icon: Mail,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-notification-templates',
        name: 'Notification Templates',
        href: '/notification-templates',
        icon: Bell,
        superAdminOnly: true,
      },
    ],
  },
  {
    id: 'superadmin-subscription',
    title: 'Subscription',
    items: [
      {
        id: 'superadmin-plans',
        name: 'Subscription Setting',
        href: '/plans',
        icon: CreditCard,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-coupons',
        name: 'Coupons',
        href: '/coupons',
        icon: Tag,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-bank-transfer',
        name: 'Bank Transfer Requests',
        href: '/bank-transfer',
        icon: Landmark,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-orders',
        name: 'Orders',
        href: '/orders',
        icon: ShoppingCart,
        superAdminOnly: true,
      },
    ],
  },
  {
    id: 'superadmin-system',
    title: 'System',
    items: [
      {
        id: 'superadmin-media',
        name: 'Media Library',
        href: '/media/page',
        icon: FolderOpen,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-addons',
        name: 'Add-ons Manager',
        href: '/modules',
        icon: PackagePlus,
        superAdminOnly: true,
      },
      {
        id: 'superadmin-settings',
        name: 'Settings',
        href: '/super-admin/settings',
        icon: Settings,
        superAdminOnly: true,
      },
    ],
  },
];
