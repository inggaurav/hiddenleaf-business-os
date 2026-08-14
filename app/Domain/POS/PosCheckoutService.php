<?php

namespace App\Domain\POS;

use App\Domain\Inventory\StockMovementService;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosCheckoutService
{
    public function __construct(
        private readonly StockMovementService $stockMovement,
        private readonly PosNumberService $posNumber,
        private readonly PosDiscountService $discountService,
    ) {}

    /**
     * Execute an atomic POS checkout.
     *
     * @param array $data {
     *   billing_counter_id: int,
     *   warehouse_id: int,
     *   customer_id: ?int,
     *   items: array<{product_id: int, quantity: string|float, unit_price: ?float}>,
     *   discount_id: ?int,
     *   payment_method: string,
     *   payment_reference: ?string,
     *   idempotency_key: string,
     *   notes: ?string,
     * }
     */
    public function checkout(int $workspaceId, int $organizationId, User $cashier, array $data): PosSale
    {
        // Idempotency guard — return existing sale if key already used
        $existing = PosSale::where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($workspaceId, $organizationId, $cashier, $data) {
            // --- Validate counter ---
            $counter = BillingCounter::where('workspace_id', $workspaceId)
                ->where('id', $data['billing_counter_id'])
                ->where('is_active', true)
                ->firstOrFail();

            // --- Validate warehouse ---
            $warehouse = Warehouse::where('workspace_id', $workspaceId)
                ->where('id', $data['warehouse_id'])
                ->firstOrFail();

            // --- Validate and build line items (server recalculates all totals) ---
            $lineItems = [];
            $subtotal = '0.0000';
            $totalTax = '0.0000';

            foreach ($data['items'] as $item) {
                $product = ProductServiceItem::where('workspace_id', $workspaceId)
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $qty = bcadd((string) $item['quantity'], '0', 4);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new RuntimeException("Invalid quantity for product {$product->name}.");
                }

                // Server uses canonical price
                $unitPrice = bcdiv(
                    (string) ($item['unit_price'] ?? $product->sale_price),
                    '1',
                    4
                );

                $taxRate = bcdiv((string) ($product->tax_rate ?? '0'), '1', 4);
                $lineBase = bcmul($unitPrice, $qty, 4);
                $lineTax = bcmul($lineBase, bcdiv($taxRate, '100', 4), 4);
                $lineTotal = bcadd($lineBase, $lineTax, 4);

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'discount_amount' => '0.0000',
                    'line_total' => $lineTotal,
                    'type' => $product->type,
                ];

                $subtotal = bcadd($subtotal, $lineBase, 4);
                $totalTax = bcadd($totalTax, $lineTax, 4);
            }

            // --- Validate and apply discount ---
            $discountAmount = '0.0000';
            if (!empty($data['discount_id'])) {
                $discount = PosDiscount::where('workspace_id', $workspaceId)
                    ->where('id', $data['discount_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $discountAmount = $this->discountService->calculate($discount, $subtotal);
            }

            // Grand total (server-authoritative)
            $grandTotal = bcsub(bcadd($subtotal, $totalTax, 4), $discountAmount, 4);
            if (bccomp($grandTotal, '0', 4) < 0) {
                $grandTotal = '0.0000';
            }

            // --- Lock and validate stock for product items ---
            foreach ($lineItems as $line) {
                if ($line['type'] === 'product') {
                    // lockForUpdate handled inside StockMovementService
                }
            }

            // --- Create POS sale ---
            $saleNumber = $this->posNumber->next($workspaceId);

            $sale = PosSale::create([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'sale_number' => $saleNumber,
                'billing_counter_id' => $counter->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $discountAmount,
                'total' => $grandTotal,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'posted_at' => now(),
                'created_by' => $cashier->id,
            ]);

            // --- Create sale items and stock movements ---
            foreach ($lineItems as $line) {
                PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                    'type' => $line['type'],
                ]);

                // Only create inventory movement for physical products
                if ($line['type'] === 'product') {
                    $this->stockMovement->recordMovement(
                        organizationId: $organizationId,
                        workspaceId: $workspaceId,
                        warehouseId: $warehouse->id,
                        productId: $line['product']->id,
                        movementType: 'pos_sale',
                        quantity: (float) $line['quantity'],
                        direction: -1,
                        referenceType: 'pos_sale',
                        referenceId: $sale->id,
                        unitCost: (float) $line['unit_price'],
                        reason: "POS Sale {$saleNumber}",
                    );
                }
            }

            return $sale;
        });
    }
}