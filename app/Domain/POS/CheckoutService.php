<?php

namespace App\Domain\POS;

use App\Domain\Accounting\Money;
use App\Domain\Inventory\StockAdjustmentService;
use App\Models\PosOrder;
use App\Models\PosSession;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutService
{
    public function __construct(private StockAdjustmentService $stock, private AuditLogger $audit) {}

    public function checkout(PosSession $session, array $data, User $actor): PosOrder
    {
        if ($session->status !== 'open') {
            throw new RuntimeException('The POS session is closed.');
        }

        // Idempotency: reject duplicate checkout requests
        if (! empty($data['idempotency_key'])) {
            $exists = PosOrder::where('organization_id', $session->organization_id)
                ->where('workspace_id', $session->workspace_id)
                ->where('receipt_number', $data['idempotency_key'])
                ->exists();
            if ($exists) {
                throw new RuntimeException('Duplicate checkout: this idempotency key has already been used.');
            }
        }

        $register = DB::table('pos_registers')
            ->where('organization_id', $session->organization_id)
            ->where('workspace_id', $session->workspace_id)
            ->find($session->register_id);
        if (! $register) {
            throw new RuntimeException('POS register is outside the active tenant.');
        }

        $products = ProductServiceItem::forTenant($session->organization_id, $session->workspace_id)
            ->where('type', 'product')
            ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
            ->get()->keyBy('id');
        if ($products->count() !== collect($data['items'])->pluck('product_id')->unique()->count()) {
            throw new RuntimeException('Every POS item must reference a tenant product.');
        }

        return DB::transaction(function () use ($session, $data, $actor, $register, $products) {
            $subtotal = Money::zero();
            $tax = Money::zero();
            $discount = Money::zero();
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $quantity = (string) $item['quantity'];
                $unit = Money::of($product->sale_price);
                $lineBase = $unit->multiply($quantity);
                $lineDiscount = $lineBase->multiply((string) (($item['discount_percent'] ?? 0) / 100));
                $lineTax = $lineBase->subtract($lineDiscount)->multiply((string) (($item['tax_percent'] ?? 0) / 100));
                $subtotal = $subtotal->add($lineBase);
                $discount = $discount->add($lineDiscount);
                $tax = $tax->add($lineTax);
                $lines[] = [
                    'product' => $product,
                    'quantity' => (float) $quantity,
                    'unit_price' => $unit->toFloat(),
                    'tax_amount' => $lineTax->toFloat(),
                    'discount_amount' => $lineDiscount->toFloat(),
                    'line_total' => $lineBase->subtract($lineDiscount)->add($lineTax)->toFloat(),
                ];
            }

            $grand = $subtotal->subtract($discount)->add($tax);
            $paid = Money::of($data['paid_amount']);

            if ($paid->isLessThan($grand)) {
                throw new RuntimeException('Payment is less than the order total.');
            }

            $receiptNumber = ! empty($data['idempotency_key'])
                ? $data['idempotency_key']
                : 'POS-'.now()->format('Ymd').'-'.str_pad(
                    (string) (PosOrder::where('workspace_id', $session->workspace_id)->count() + 1),
                    6, '0', STR_PAD_LEFT
                );

            $order = PosOrder::create([
                'organization_id' => $session->organization_id,
                'workspace_id' => $session->workspace_id,
                'session_id' => $session->id,
                'receipt_number' => $receiptNumber,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'subtotal' => $subtotal->toFloat(),
                'tax_total' => $tax->toFloat(),
                'discount_total' => $discount->toFloat(),
                'grand_total' => $grand->toFloat(),
                'paid_amount' => $paid->toFloat(),
                'change_amount' => $paid->subtract($grand)->toFloat(),
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'created_by' => $actor->id,
            ]);

            $warehouse = Warehouse::where('organization_id', $session->organization_id)
                ->where('workspace_id', $session->workspace_id)
                ->findOrFail($register->warehouse_id);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['tax_amount'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                ]);
                $this->stock->adjust(
                    $line['product'], $warehouse, -$line['quantity'],
                    'POS sale '.$order->receipt_number, $actor, 'pos_sale', $order
                );
            }

            $this->audit->log(
                $actor->id, $session->organization_id, $session->workspace_id,
                'pos.checkout', 'pos_order', (string) $order->id,
                ['receipt_number' => $order->receipt_number, 'grand_total' => $grand->toFloat(), 'payment_method' => $data['payment_method']],
                critical: true
            );

            return $order->load('items');
        });
    }
}
