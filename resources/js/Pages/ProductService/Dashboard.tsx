import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Package,
  Boxes,
  Warehouse,
  ArrowLeftRight,
  AlertTriangle,
  Tags,
  Plus,
  ArrowRight,
  Layers,
  CheckCircle2,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ProgressDistribution } from '@/Components/UI/Charts';

interface InventoryStats {
  total_products: number;
  total_services: number;
  total_categories: number;
  total_units: number;
  total_warehouses: number;
  total_stock_units: number;
  low_stock_items: number;
  out_of_stock_items: number;
  total_transfers: number;
}

interface WarehouseDistItem {
  warehouse_id: number;
  name: string;
  stock_units: number;
}

interface MovementItem {
  id: number;
  product_name: string;
  warehouse_name: string;
  quantity: number;
  type: string;
  reason: string;
  created_at: string;
}

interface TransferItem {
  id: number;
  transfer_number: string;
  from_warehouse: string;
  to_warehouse: string;
  product_name: string;
  quantity: number;
  date: string;
  status: string;
}

interface ProductServiceDashboardProps {
  stats: InventoryStats;
  warehouseDistribution: WarehouseDistItem[];
  recentMovements: MovementItem[];
  recentTransfers: TransferItem[];
}

export default function ProductServiceDashboard({
  stats,
  warehouseDistribution = [],
  recentMovements = [],
  recentTransfers = [],
}: ProductServiceDashboardProps) {
  const warehouseItems = warehouseDistribution.map((w) => ({
    name: w.name,
    value: w.stock_units,
    formattedValue: `${w.stock_units} units`,
  }));

  return (
    <AppShell title="Inventory Dashboard">
      <Head title="Product & Inventory Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="Product & Inventory Dashboard"
            description="Catalog breakdown, multi-warehouse stock distribution, low inventory thresholds, and transfers."
          />
          <div className="flex items-center gap-3">
            <Link
              href="/warehouses"
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-2)] px-4 py-2.5 text-xs font-semibold text-[var(--text-primary)] border border-[var(--border-subtle)] hover:bg-[var(--surface-3)] transition"
            >
              <Warehouse className="h-4 w-4 text-[var(--text-secondary)]" />
              Warehouses
            </Link>
            <Link
              href="/product-service"
              className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition"
            >
              <Package className="h-4 w-4" />
              Item Catalog
            </Link>
          </div>
        </div>

        {/* Primary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Physical Products"
            value={stats.total_products}
            icon={<Package className="h-5 w-5 text-indigo-400" />}
            subtitle={`${stats.total_services} service items`}
          />
          <MetricCard
            title="Total Stock Units"
            value={stats.total_stock_units}
            icon={<Boxes className="h-5 w-5 text-emerald-400" />}
            subtitle={`Across ${stats.total_warehouses} warehouses`}
          />
          <MetricCard
            title="Low Stock Items"
            value={stats.low_stock_items}
            icon={<AlertTriangle className="h-5 w-5 text-amber-400" />}
            trend={{
              value: stats.low_stock_items > 0 ? 'Restock needed' : 'Optimal',
              positive: stats.low_stock_items === 0,
            }}
          />
          <MetricCard
            title="Out of Stock"
            value={stats.out_of_stock_items}
            icon={<AlertTriangle className="h-5 w-5 text-rose-400" />}
            trend={{
              value: stats.out_of_stock_items > 0 ? 'Zero inventory' : 'None',
              positive: stats.out_of_stock_items === 0,
            }}
          />
        </div>

        {/* Secondary Metric Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Warehouses"
            value={stats.total_warehouses}
            icon={<Warehouse className="h-5 w-5 text-sky-400" />}
            subtitle="Storage facilities"
          />
          <MetricCard
            title="Item Categories"
            value={stats.total_categories}
            icon={<Tags className="h-5 w-5 text-violet-400" />}
            subtitle={`${stats.total_units} measurement units`}
          />
          <MetricCard
            title="Stock Transfers"
            value={stats.total_transfers}
            icon={<ArrowLeftRight className="h-5 w-5 text-indigo-400" />}
            subtitle="Inter-warehouse relocations"
          />
          <MetricCard
            title="Inventory Health"
            value={stats.out_of_stock_items === 0 && stats.low_stock_items === 0 ? 'Optimal' : 'Attention'}
            icon={<CheckCircle2 className="h-5 w-5 text-emerald-400" />}
            subtitle="Catalog availability status"
          />
        </div>

        {/* Warehouse Stock Distribution */}
        <Card level={0} className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Warehouse Stock Distribution
              </h3>
              <p className="text-xs text-[var(--text-tertiary)]">
                Current inventory levels allocated per physical warehouse location
              </p>
            </div>
            <Badge variant="neutral">{stats.total_warehouses} Facilities</Badge>
          </div>
          {warehouseDistribution.length === 0 ? (
            <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
              No warehouses configured in this workspace.
            </div>
          ) : (
            <ProgressDistribution items={warehouseItems} />
          )}
        </Card>

        {/* Recent Activity Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Stock Movements */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Stock Movements
              </h3>
              <Link
                href="/product-service"
                className="text-xs text-violet-400 hover:underline"
              >
                View catalog
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentMovements.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No stock movements recorded.
                </div>
              ) : (
                recentMovements.map((move) => (
                  <div key={move.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {move.product_name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {move.warehouse_name} • {move.reason}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {move.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div
                        className={`text-xs font-bold ${
                          move.quantity >= 0 ? 'text-emerald-400' : 'text-rose-400'
                        }`}
                      >
                        {move.quantity >= 0 ? `+${move.quantity}` : move.quantity}
                      </div>
                      <Badge variant="neutral" size="sm">
                        {move.type}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Transfers */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Warehouse Transfers
              </h3>
              <Link
                href="/transfers"
                className="text-xs text-violet-400 hover:underline"
              >
                View transfers
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentTransfers.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No transfers logged.
                </div>
              ) : (
                recentTransfers.map((trf) => (
                  <div key={trf.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {trf.transfer_number}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {trf.from_warehouse} → {trf.to_warehouse}
                      </div>
                      <div className="text-[10px] text-[var(--text-tertiary)]">
                        {trf.product_name} ({trf.quantity} units) • {trf.date}
                      </div>
                    </div>
                    <Badge variant={trf.status === 'completed' ? 'success' : 'neutral'} size="sm">
                      {trf.status}
                    </Badge>
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
