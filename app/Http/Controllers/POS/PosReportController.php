<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosReportController extends Controller
{
    public function sales(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $query = PosSale::with(['cashier:id,name', 'counter:id,name'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'completed');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('counter_id')) {
            $query->where('billing_counter_id', $request->input('counter_id'));
        }

        $sales = $query->latest()->paginate(15)->withQueryString();

        $summary = [
            'total_sales' => (float) (clone $query)->sum('total'),
            'total_tax' => (float) (clone $query)->sum('tax_amount'),
            'total_discount' => (float) (clone $query)->sum('discount_amount'),
            'orders_count' => (clone $query)->count(),
        ];

        return Inertia::render('POS/Reports/Sales', [
            'sales' => $sales,
            'summary' => $summary,
            'counters' => BillingCounter::where('organization_id', $orgId)->where('workspace_id', $wsId)->get(),
        ]);
    }

    public function products(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $query = PosSaleItem::join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->join('product_service_items', 'product_service_items.id', '=', 'pos_sale_items.product_id')
            ->where('pos_sales.organization_id', $orgId)
            ->where('pos_sales.workspace_id', $wsId)
            ->where('pos_sales.status', 'completed');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('pos_sales.created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        if ($request->filled('product_id')) {
            $query->where('pos_sale_items.product_id', $request->input('product_id'));
        }

        $products = $query->select(
            'product_service_items.id',
            'product_service_items.name',
            'product_service_items.sku',
            'pos_sale_items.type',
            DB::raw('SUM(pos_sale_items.quantity) as total_quantity'),
            DB::raw('SUM(pos_sale_items.line_total) as total_revenue'),
            DB::raw('COUNT(DISTINCT pos_sales.id) as orders_count')
        )
            ->groupBy('product_service_items.id', 'product_service_items.name', 'product_service_items.sku', 'pos_sale_items.type')
            ->orderByDesc('total_quantity')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('POS/Reports/Products', [
            'products' => $products,
        ]);
    }

    public function customers(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $query = PosSale::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('status', 'completed');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        $customerSales = $query->select(
            'customer_id',
            'payment_method',
            DB::raw('COUNT(id) as total_orders'),
            DB::raw('SUM(total) as total_spent'),
            DB::raw('MAX(created_at) as last_order_date')
        )
            ->groupBy('customer_id', 'payment_method')
            ->orderByDesc('total_spent')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('POS/Reports/Customers', [
            'customerSales' => $customerSales,
        ]);
    }
}
