<?php

namespace App\Domain\POS;

use App\Domain\Accounting\Money;
use App\Domain\Inventory\InventoryQuantity;
use App\Domain\Inventory\StockMovementService;
use App\Models\AccountCustomer;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
use App\Models\POS\PosIdempotencyKey;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PosCheckoutService
{
    public function __construct(
        private readonly StockMovementService $stockMovement,
        private readonly PosNumberService $posNumber,
        private readonly PosDiscountService $discountService,
        private readonly PosAccountingService $accounting,
    ) {}

    public function checkout(int $workspaceId, int $organizationId, User $cashier, array $data): PosSale
    {
        $key = (string) ($data['idempotency_key'] ?? '');
        if ($key === '' || strlen($key) > 64) {
            throw new RuntimeException('A valid idempotency key is required for POS checkout.');
        }
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($workspaceId, $organizationId, $cashier, $data, $key, $fingerprint) {
            // Durable per-workspace idempotency row. insertOrIgnore + unique key
            // closes the first-request race; lockForUpdate serializes retries.
            DB::table('pos_idempotency_keys')->insertOrIgnore([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'pos_sale_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $operation = PosIdempotencyKey::query()
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals((string) $operation->request_fingerprint, $fingerprint)) {
                throw new ConflictHttpException('This POS idempotency key was already used with a different checkout payload.');
            }

            if ($operation->pos_sale_id) {
                return PosSale::query()
                    ->where('organization_id', $organizationId)
                    ->where('workspace_id', $workspaceId)
                    ->findOrFail($operation->pos_sale_id);
            }

            // Compatibility with sales created before pos_idempotency_keys was
            // introduced. Never resolve an idempotency key outside this tenant.
            $existing = PosSale::query()
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->where('idempotency_key', $key)
                ->first();
            if ($existing) {
                if (! $existing->request_fingerprint || ! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw new ConflictHttpException('This POS idempotency key belongs to a different checkout payload.');
                }
                $operation->update(['pos_sale_id' => $existing->id]);
                return $existing;
            }

            $counter = BillingCounter::query()
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->where('id', $data['billing_counter_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $warehouse = Warehouse::query()
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->where('id', $data['warehouse_id'])
                ->where('is_active', true)
                ->firstOrFail();

            if ($counter->warehouse_id !== null && (int) $counter->warehouse_id !== (int) $warehouse->id) {
                throw new RuntimeException('The selected billing counter is bound to a different warehouse.');
            }

            if (! empty($data['customer_id'])) {
                AccountCustomer::forWorkspace($organizationId, $workspaceId)->findOrFail((int) $data['customer_id']);
            }

            $lineItems = [];
            $subtotal = Money::zero(4);
            $totalTax = Money::zero(4);

            foreach ($data['items'] as $item) {
                $product = ProductServiceItem::query()
                    ->where('organization_id', $organizationId)
                    ->where('workspace_id', $workspaceId)
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $qty = InventoryQuantity::of((string) $item['quantity']);
                if (! $qty->isPositive()) {
                    throw new RuntimeException("Invalid quantity for product {$product->name}.");
                }

                // Price is server-authoritative. Client-supplied unit_price is
                // deliberately ignored to prevent cart tampering.
                $unitPrice = Money::of((string) $product->sale_price, 4);
                $taxRate = bcadd((string) ($product->tax_rate ?? '0'), '0', 4);
                $lineBase = $unitPrice->multiplyByDecimal($qty->toStorageString());
                $lineTax = $lineBase->multiplyByDecimal(bcdiv($taxRate, '100', 8));

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'line_base' => $lineBase,
                ];

                $subtotal = $subtotal->add($lineBase);
                $totalTax = $totalTax->add($lineTax);
            }

            $discountAmount = Money::zero(4);
            if (! empty($data['discount_id'])) {
                $discount = PosDiscount::query()
                    ->where('organization_id', $organizationId)
                    ->where('workspace_id', $workspaceId)
                    ->where('id', $data['discount_id'])
                    ->where('is_active', true)
                    ->firstOrFail();
                $discountAmount = Money::of($this->discountService->calculate($discount, $subtotal->toStorageString()), 4);
            }

            $grandTotal = $subtotal->add($totalTax)->subtract($discountAmount);
            if ($grandTotal->isNegative()) {
                $grandTotal = Money::zero(4);
            }

            // Allocate order discount proportionally to line base so partial
            // returns can calculate exact refundable line amounts.
            $allocated = Money::zero(4);
            $lastIndex = count($lineItems) - 1;
            foreach ($lineItems as $index => &$line) {
                if ($discountAmount->isZero() || $subtotal->isZero()) {
                    $lineDiscount = Money::zero(4);
                } elseif ($index === $lastIndex) {
                    $lineDiscount = $discountAmount->subtract($allocated);
                } else {
                    $ratio = bcdiv($line['line_base']->toStorageString(), $subtotal->toStorageString(), 8);
                    $lineDiscount = $discountAmount->multiplyByDecimal($ratio);
                    $allocated = $allocated->add($lineDiscount);
                }

                $line['discount_amount'] = $lineDiscount;
                $line['line_total'] = $line['line_base']->add($line['tax_amount'])->subtract($lineDiscount);
            }
            unset($line);

            $saleNumber = $this->posNumber->next($workspaceId);
            $sale = PosSale::create([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'sale_number' => $saleNumber,
                'billing_counter_id' => $counter->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'subtotal' => $subtotal->toStorageString(),
                'tax_amount' => $totalTax->toStorageString(),
                'discount_amount' => $discountAmount->toStorageString(),
                'total' => $grandTotal->toStorageString(),
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'posted_at' => now(),
                'created_by' => $cashier->id,
            ]);

            foreach ($lineItems as $line) {
                $saleItem = PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'quantity' => $line['quantity']->toStorageString(),
                    'unit_price' => $line['unit_price']->toStorageString(),
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount']->toStorageString(),
                    'discount_amount' => $line['discount_amount']->toStorageString(),
                    'line_total' => $line['line_total']->toStorageString(),
                    'type' => $line['product']->type,
                ]);

                if ($line['product']->type === 'product') {
                    $this->stockMovement->recordMovement(
                        organizationId: $organizationId,
                        workspaceId: $workspaceId,
                        warehouseId: $warehouse->id,
                        productId: $line['product']->id,
                        movementType: 'pos_sale',
                        quantity: $line['quantity'],
                        direction: -1,
                        referenceType: 'pos_sale',
                        referenceId: $sale->id,
                        unitCost: Money::of((string) ($line['product']->purchase_price ?? '0'), 4),
                        reason: "POS Sale {$saleNumber}",
                        actor: $cashier,
                        referenceLineId: $saleItem->id,
                    );
                }
            }

            // Financial posting is inside this transaction. If the journal
            // cannot be created or balanced, sale/items/stock all roll back.
            $this->accounting->postSale($sale, $cashier);
            $operation->update(['pos_sale_id' => $sale->id]);

            return $sale->refresh();
        });
    }

    private function fingerprint(array $data): string
    {
        $items = collect($data['items'] ?? [])->map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'quantity' => InventoryQuantity::of((string) $item['quantity'])->toStorageString(),
        ])->sortBy(fn (array $item) => sprintf('%020d:%s', $item['product_id'], $item['quantity']))->values()->all();

        $payload = [
            'billing_counter_id' => (int) ($data['billing_counter_id'] ?? 0),
            'warehouse_id' => (int) ($data['warehouse_id'] ?? 0),
            'customer_id' => empty($data['customer_id']) ? null : (int) $data['customer_id'],
            'discount_id' => empty($data['discount_id']) ? null : (int) $data['discount_id'],
            'payment_method' => strtolower((string) ($data['payment_method'] ?? '')),
            'payment_reference' => $data['payment_reference'] ?? null,
            'items' => $items,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
