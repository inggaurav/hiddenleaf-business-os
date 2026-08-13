import React from 'react';
import {
  PlusCircle,
  FilePlus,
  PackagePlus,
  UserPlus,
  UploadCloud,
  Send,
  Sparkles,
  LifeBuoy,
} from 'lucide-react';
import { isItemAuthorized, NavigationItem } from './NavigationRegistry';

export interface ActionItem extends NavigationItem {
  shortcut?: string;
}

export const ALL_QUICK_ACTIONS: ActionItem[] = [
  {
    id: 'action-new-sales-invoice',
    name: 'Create Sales Invoice',
    href: '/sales-invoices/create',
    icon: FilePlus,
    permission: 'sales.invoice.view',
    category: 'Sales Action',
    description: 'Issue a new customer invoice with itemized entries',
  },
  {
    id: 'action-new-sales-proposal',
    name: 'New Sales Proposal',
    href: '/sales-proposals/create',
    icon: FilePlus,
    permission: 'sales.proposal.view',
    category: 'Sales Action',
    description: 'Draft a quotation or commercial proposal',
  },
  {
    id: 'action-new-warehouse',
    name: 'Add New Warehouse',
    href: '/warehouses/create',
    icon: PackagePlus,
    permission: 'warehouses.view',
    category: 'Inventory Action',
    description: 'Register a physical or virtual storage location',
  },
  {
    id: 'action-new-user',
    name: 'Invite Team Member',
    href: '/users/create',
    icon: UserPlus,
    permission: 'users.view',
    category: 'Security Action',
    description: 'Onboard a new user into the active organization',
  },
  {
    id: 'action-upload-media',
    name: 'Upload Media Asset',
    href: '/media',
    icon: UploadCloud,
    permission: 'media.view',
    category: 'Storage Action',
    description: 'Upload files and documents to S3 storage',
  },
  {
    id: 'action-new-ticket',
    name: 'Submit Helpdesk Ticket',
    href: '/helpdesk-tickets/create',
    icon: LifeBuoy,
    permission: 'helpdesk.view',
    category: 'Support Action',
    description: 'File an operational support ticket',
  },
];

export function filterActions(
  actions: ActionItem[],
  user: any,
  isSuperAdmin: boolean,
  userPermissions: string[],
  enabledModules: string[] = []
): ActionItem[] {
  return actions.filter((action) =>
    isItemAuthorized(action, user, isSuperAdmin, userPermissions, enabledModules)
  );
}
