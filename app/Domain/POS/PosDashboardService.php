<?php

namespace App\Domain\POS;

use App\Models\POS\BillingCounter;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PosDashboardService
{
    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId  = $workspace->id;
        $today = Carbon::today();

        // ── Today's completed sales ─────────────────────────────────────────
        $todayBase  = PosSale::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'completed')
            ->whereDate('created_at', $today);

        $todaySalesCount = (clone $todayBase)->count();
        $todayRevenue    = (float) (clone $todayBase)->sum('total');

        // ── All-time completed sales ────────────────────────────────────────
        $allSales       = PosSale::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'completed');

        $totalSalesCount = (clone $allSales)->count();
        $totalRevenue    = (float) (clone $allSales)->sum('total');
        $avgOrderValue   = $totalSalesCount > 0 ? round($totalRevenue / $totalSalesCount, 2) : 0;

        // ── Billing counters ────────────────────────────────────────────────
        $activeCounters = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true)
            ->count();

        $totalCounters  = BillingCounter::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->count();

        // ── Catalogue counts ────────────────────────────────────────────────
        $totalProducts = ProductServiceItem::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('type', 'product')
            ->count();

        // ── Returns ─────────────────────────────────────────────────────────
        $returnsBase   = PosReturn::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalReturns  = (clone $returnsBase)->count();
        $returnsAmount = (float) (clone $returnsBase)->sum('refund_amount');

        // ── Payment method breakdown ─────────────────────────────────────────
        $paymentBreakdown = (clone $allSales)
            ->select('payment_method', DB::raw('SUM(total) as total_amount'))
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // ── Last 10 days sales sparkline ─────────────────────────────────────
        $last10DaysSales = [];
        for ($i = 9; $i >= 0; $i--) {
            $date    = Carbon::now()->subDays($i);
            $daySales = (float) PosSale::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('total');

            $last10DaysSales[] = ['date' => $date->format('M d'), 'sales' => $daySales];
        }

        // ── Top 5 products ───────────────────────────────────────────────────
        $topProducts = PosSaleItem::join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->join('product_service_items', 'product_service_items.id', '=', 'pos_sale_items.product_id')
            ->where('pos_sales.organization_id', $orgId)
            ->where('pos_sales.workspace_id', $wsId)
            ->where('pos_sales.status', 'completed')
            ->select(
                'product_service_items.name',
                'product_service_items.sku',
                DB::raw('SUM(pos_sale_items.quantity) as total_quantity'),
                DB::raw('SUM(pos_sale_items.line_total) as total_revenue')
            )
            ->groupBy('pos_sale_items.product_id', 'product_service_items.name', 'product_service_items.sku')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'name'           => $p->name,
                'sku'            => $p->sku ?? '—',
                'total_quantity' => (float) $p->total_quantity,
                'total_revenue'  => (float) $p->total_revenue,
            ]);

        // ── Low stock ────────────────────────────────────────────────────────
        $warehouseIds = Warehouse::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->pluck('id');

        $lowStockCount = WarehouseStock::whereIn('warehouse_id', $warehouseIds)
            ->where('quantity', '<=', '5.0000')
            ->count();

        // ── Recent sales ─────────────────────────────────────────────────────
        $recentOrders = PosSale::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->with('cashier:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($o) => [
                'id'             => $o->id,
                'sale_number'    => $o->sale_number,
                'cashier'        => $o->cashier?->name ?? '—',
                'payment_method' => ucfirst($o->payment_method),
                'total'          => (float) $o->total,
                'status'         => $o->status,
                'created_at'     => $o->created_at->format('M d, Y H:i'),
            ]);

        // ── Recent returns ───────────────────────────────────────────────────
        $recentReturns = PosReturn::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'            => $r->id,
                'return_number' => $r->return_number,
                'refund_amount' => (float) $r->refund_amount,
                'reason'        => $r->reason,
                'status'        => $r->status,
                'created_at'    => $r->created_at->format('M d, Y H:i'),
            ]);

        return [
            'stats' => [
                'today_orders'    => $todaySalesCount,
                'today_revenue'   => $todayRevenue,
                'total_sales'     => $totalSalesCount,
                'total_revenue'   => $totalRevenue,
                'avg_order_value' => $avgOrderValue,
                'active_counters' => $activeCounters,
                'total_counters'  => $totalCounters,
                'total_products'  => $totalProducts,
                'total_returns'   => $totalReturns,
                'returns_amount'  => $returnsAmount,
                'low_stock_count' => $lowStockCount,
            ],
            'paymentBreakdown' => $paymentBreakdown,
            'last10DaysSales'  => $last10DaysSales,
            'topProducts'      => $topProducts,
            'recentOrders'     => $recentOrders,
            'recentReturns'    => $recentReturns,
        ];
    }
}
