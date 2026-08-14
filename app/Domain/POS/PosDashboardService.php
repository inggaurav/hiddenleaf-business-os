<?php

namespace App\Domain\POS;

use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosRegister;
use App\Models\PosSession;
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
        $wsId = $workspace->id;
        $today = Carbon::today();

        $todayOrders = PosOrder::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->whereDate('created_at', $today);

        $todaySalesCount = (clone $todayOrders)->where('status', 'completed')->count();
        $todayRevenue = (float) (clone $todayOrders)->where('status', 'completed')->sum('grand_total');

        $allOrders = PosOrder::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'completed');
        $totalSalesCount = (clone $allOrders)->count();
        $totalRevenue = (float) (clone $allOrders)->sum('grand_total');

        $avgOrderValue = $totalSalesCount > 0 ? round($totalRevenue / $totalSalesCount, 2) : 0;

        $openRegisters = PosSession::where('organization_id', $orgId)->where('workspace_id', $wsId)->where('status', 'open')->count();
        $totalRegisters = PosRegister::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();

        $totalProducts = ProductServiceItem::forTenant($orgId, $wsId)->where('type', 'product')->count();

        $totalReturns = DB::table('pos_returns')->where('organization_id', $orgId)->where('workspace_id', $wsId)->count();
        $returnsAmount = (float) DB::table('pos_returns')->where('organization_id', $orgId)->where('workspace_id', $wsId)->sum('refund_total');

        // Payment method breakdown
        $paymentBreakdown = (clone $allOrders)
            ->select('payment_method', DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($val) => (float) $val)
            ->toArray();

        // Last 10 days sales
        $last10DaysSales = [];
        for ($i = 9; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $daySales = (float) PosOrder::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('grand_total');

            $last10DaysSales[] = [
                'date' => $date->format('M d'),
                'sales' => $daySales,
            ];
        }

        // Top products by quantity
        $topProducts = PosOrderItem::join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->join('product_service_items', 'product_service_items.id', '=', 'pos_order_items.product_id')
            ->where('pos_orders.organization_id', $orgId)
            ->where('pos_orders.workspace_id', $wsId)
            ->where('pos_orders.status', 'completed')
            ->select(
                'product_service_items.name',
                'product_service_items.sku',
                DB::raw('SUM(pos_order_items.quantity) as total_quantity'),
                DB::raw('SUM(pos_order_items.line_total) as total_revenue')
            )
            ->groupBy('pos_order_items.product_id', 'product_service_items.name', 'product_service_items.sku')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'sku' => $p->sku ?? '—',
                'total_quantity' => (float) $p->total_quantity,
                'total_revenue' => (float) $p->total_revenue,
            ]);

        // Low stock products count
        $warehouseIds = Warehouse::where('organization_id', $orgId)->where('workspace_id', $wsId)->pluck('id');
        $lowStockCount = WarehouseStock::whereIn('warehouse_id', $warehouseIds)->where('quantity', '<=', 5)->count();

        // Recent POS Orders
        $recentOrders = PosOrder::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'receipt_number' => $o->receipt_number,
                'customer_name' => $o->customer_name ?? 'Walk-in Customer',
                'payment_method' => ucfirst($o->payment_method),
                'grand_total' => (float) $o->grand_total,
                'status' => $o->status,
                'created_at' => $o->created_at->format('M d, Y H:i'),
            ]);

        // Recent Returns
        $recentReturns = DB::table('pos_returns')
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'refund_total' => (float) $r->refund_total,
                'reason' => $r->reason,
                'created_at' => Carbon::parse($r->created_at)->format('M d, Y H:i'),
            ]);

        return [
            'stats' => [
                'today_orders' => $todaySalesCount,
                'today_revenue' => $todayRevenue,
                'total_sales' => $totalSalesCount,
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $avgOrderValue,
                'open_registers' => $openRegisters,
                'total_registers' => $totalRegisters,
                'total_products' => $totalProducts,
                'total_returns' => $totalReturns,
                'returns_amount' => $returnsAmount,
                'low_stock_count' => $lowStockCount,
            ],
            'paymentBreakdown' => $paymentBreakdown,
            'last10DaysSales' => $last10DaysSales,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'recentReturns' => $recentReturns,
        ];
    }
}
