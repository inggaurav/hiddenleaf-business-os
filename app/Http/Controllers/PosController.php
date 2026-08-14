<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\StockAdjustmentService;
use App\Domain\POS\CheckoutService;
use App\Models\PosOrder;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\ProductServiceItem;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\POS\PosDashboardService;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PosController extends Controller
{
    public function dashboard(Request $request, PosDashboardService $dashboardService)
    {
        $w = $this->workspace($request);
        $data = $dashboardService->getMetrics($w);

        return Inertia::render('POS/Dashboard', ['metrics' => $data['stats']] + $data);
    }

    public function index(Request $request)
    {
        $w = $this->workspace($request);
        $todayOrders = PosOrder::forWorkspace($w->organization_id, $w->id)->whereDate('created_at', today());

        return Inertia::render('POS/Index', ['registers' => PosRegister::where('workspace_id', $w->id)->get(), 'sessions' => PosSession::where('workspace_id', $w->id)->latest()->limit(20)->get(), 'orders' => PosOrder::forWorkspace($w->organization_id, $w->id)->with('items')->latest()->paginate(30), 'metrics' => ['today_orders' => (clone $todayOrders)->where('status', 'completed')->count(), 'today_revenue' => (float) (clone $todayOrders)->where('status', 'completed')->sum('grand_total'), 'payment_breakdown' => (clone $todayOrders)->where('status', 'completed')->select('payment_method', DB::raw('SUM(grand_total) as aggregate'))->groupBy('payment_method')->pluck('aggregate', 'payment_method')->map(fn ($amount) => (float) $amount), 'open_registers' => PosSession::where('workspace_id', $w->id)->where('status', 'open')->count(), 'low_stock' => DB::table('warehouse_stocks')->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')->where('warehouses.workspace_id', $w->id)->where('warehouse_stocks.quantity', '<=', 5)->count()]]);
    }

    public function storeRegister(Request $request)
    {
        $w = $this->workspace($request);
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:100']]);
        Warehouse::where('organization_id', $w->organization_id)->where('workspace_id', $w->id)->findOrFail($data['warehouse_id']);
        PosRegister::create($data + ['organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'is_active' => true]);

        return back()->with('success', 'POS register created.');
    }

    public function open(Request $request, PosRegister $register)
    {
        $w = $this->workspace($request);
        $this->tenant($register, $w);
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0']]);
        abort_if(PosSession::where('register_id', $register->id)->where('status', 'open')->exists(), 422, 'Register already has an open session.');
        PosSession::create(['organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'register_id' => $register->id, 'opened_by' => $request->user()->id, 'opening_cash' => $data['opening_cash'], 'status' => 'open', 'opened_at' => now()]);

        return back()->with('success', 'Register session opened.');
    }

    public function lookup(Request $request)
    {
        $w = $this->workspace($request);
        $search = $request->validate(['query' => ['required', 'string', 'max:100']])['query'];

        return response()->json(ProductServiceItem::forTenant($w->organization_id, $w->id)->where('type', 'product')->where('is_active', true)->where(fn ($q) => $q->where('barcode', $search)->orWhere('sku', $search)->orWhere('name', 'like', "%{$search}%"))->limit(20)->get());
    }

    public function checkout(Request $request, PosSession $session, CheckoutService $checkout)
    {
        $w = $this->workspace($request);
        $this->tenant($session, $w);
        $data = $request->validate(['customer_name' => ['nullable', 'string'], 'customer_email' => ['nullable', 'email'], 'payment_method' => ['required', Rule::in(['cash', 'card', 'bank'])], 'paid_amount' => ['required', 'numeric', 'min:0'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.tax_percent' => ['nullable', 'numeric', 'between:0,100'], 'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100']]);
        $order = $checkout->checkout($session, $data, $request->user());

        return back()->with('success', 'Checkout completed: '.$order->receipt_number);
    }

    public function close(Request $request, PosSession $session)
    {
        $w = $this->workspace($request);
        $this->tenant($session, $w);
        abort_unless($session->status === 'open', 422);
        $data = $request->validate(['closing_cash' => ['required', 'numeric', 'min:0']]);
        $cashSales = (float) PosOrder::where('session_id', $session->id)->where('status', 'completed')->where('payment_method', 'cash')->sum('grand_total');
        $cashRefunds = (float) DB::table('pos_returns')->join('pos_orders', 'pos_orders.id', '=', 'pos_returns.order_id')->where('pos_orders.session_id', $session->id)->where('pos_orders.payment_method', 'cash')->sum('pos_returns.refund_total');
        $expected = (float) $session->opening_cash + $cashSales - $cashRefunds;
        $session->update(['closing_cash' => $data['closing_cash'], 'expected_cash' => $expected, 'variance' => (float) $data['closing_cash'] - $expected, 'closed_by' => $request->user()->id, 'closed_at' => now(), 'status' => 'closed']);

        return back()->with('success', 'Register session closed.');
    }

    public function refund(Request $request, PosOrder $order, StockAdjustmentService $stock)
    {
        $w = $this->workspace($request);
        $this->tenant($order, $w);
        $data = $request->validate(['reason' => ['required', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.order_item_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'numeric', 'gt:0']]);
        $session = PosSession::findOrFail($order->session_id);
        $register = PosRegister::findOrFail($session->register_id);
        $warehouse = Warehouse::where('workspace_id', $w->id)->findOrFail($register->warehouse_id);
        DB::transaction(function () use ($data, $order, $w, $request, $warehouse, $stock) {
            $items = $order->items->keyBy('id');
            $refund = 0;
            $lines = [];
            foreach ($data['items'] as $item) {
                abort_unless(isset($items[$item['order_item_id']]), 422);
                $already = (float) DB::table('pos_return_items')->join('pos_returns', 'pos_returns.id', '=', 'pos_return_items.return_id')->where('pos_returns.order_id', $order->id)->where('order_item_id', $item['order_item_id'])->sum('quantity');
                abort_if($already + (float) $item['quantity'] > (float) $items[$item['order_item_id']]->quantity, 422, 'Return quantity exceeds purchased quantity.');
                $amount = round((float) $items[$item['order_item_id']]->line_total * ((float) $item['quantity'] / (float) $items[$item['order_item_id']]->quantity), 2);
                $refund += $amount;
                $lines[] = $item + ['refund_amount' => $amount];
            }$returnId = DB::table('pos_returns')->insertGetId(['organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'order_id' => $order->id, 'return_number' => 'RET-'.now()->format('YmdHis'), 'refund_total' => $refund, 'reason' => $data['reason'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('pos_return_items')->insert(['return_id' => $returnId, 'order_item_id' => $line['order_item_id'], 'quantity' => $line['quantity'], 'refund_amount' => $line['refund_amount'], 'created_at' => now(), 'updated_at' => now()]);
                $orderItem = $items[$line['order_item_id']];
                $product = ProductServiceItem::forTenant($w->organization_id, $w->id)->findOrFail($orderItem->product_id);
                $stock->adjust($product, $warehouse, (float) $line['quantity'], 'POS return '.$returnId, $request->user(), 'pos_return');
            }
        });

        return back()->with('success', 'POS return completed.');
    }

    private function workspace(Request $r): Workspace
    {
        $w = Workspace::with('organization')->find($r->session()->get('active_workspace_id'));
        abort_unless($w && $r->user()->canInWorkspace('pos.manage', $w), 403);

        return $w;
    }

    private function tenant($m, Workspace $w): void
    {
        abort_unless((int) $m->organization_id === (int) $w->organization_id && (int) $m->workspace_id === (int) $w->id, 404);
    }
}
