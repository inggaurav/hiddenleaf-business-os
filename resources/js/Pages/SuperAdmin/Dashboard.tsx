import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ShieldCheck, Building, Users, CreditCard, Layers, DollarSign, Sliders } from 'lucide-react';

export default function SuperAdminDashboard() {
  const { totalUsers = 0, totalOrders = 0, totalPlans = 0, totalWorkspaces = 0 } = usePage<any>().props;

  return (
    <AppShell title="Super Admin Control Center">
      <div className="space-y-6">
        <SectionHeader
          title="Super Admin Control Center"
          description="Master tenant telemetry, cross-organization licensing, billing orders, and global system configuration."
          badge={<Badge variant="purple" size="sm">Global Root</Badge>}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/super-admin/settings">
                <Button variant="secondary" size="sm" icon={<Sliders className="w-4 h-4" />}>
                  Global Settings
                </Button>
              </Link>
              <Link href="/plans">
                <Button variant="primary" size="sm" icon={<CreditCard className="w-4 h-4" />}>
                  Manage Plans
                </Button>
              </Link>
            </div>
          }
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Global Tenant Users"
            value={totalUsers}
            icon={<Users className="w-5 h-5 text-violet-400" />}
            trend={{ value: 'Active', positive: true, label: 'across tenants' }}
            subtitle="Registered accounts"
          />

          <MetricCard
            title="Total Workspaces"
            value={totalWorkspaces}
            icon={<Layers className="w-5 h-5 text-indigo-400" />}
            trend={{ value: 'Healthy', positive: true, label: 'operational pods' }}
            subtitle="Isolated namespaces"
          />

          <MetricCard
            title="SaaS Plans"
            value={totalPlans}
            icon={<CreditCard className="w-5 h-5 text-purple-400" />}
            trend={{ value: 'Available', neutral: true, label: 'tiers configured' }}
            subtitle="Commercial catalog"
          />

          <MetricCard
            title="Processed Orders"
            value={totalOrders}
            icon={<DollarSign className="w-5 h-5 text-emerald-400" />}
            trend={{ value: 'Audited', positive: true, label: 'payment receipts' }}
            subtitle="Billing revenue"
          />
        </div>
      </div>
    </AppShell>
  );
}
