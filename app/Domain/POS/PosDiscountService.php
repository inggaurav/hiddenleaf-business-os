<?php

namespace App\Domain\POS;

use App\Models\POS\PosDiscount;
use RuntimeException;

class PosDiscountService
{
    /**
     * Calculate discount amount against order subtotal.
     * Returns BCMath string with 4 decimal places.
     */
    public function calculate(PosDiscount $discount, string $subtotal): string
    {
        // Check date validity
        if ($discount->valid_from && now()->lt($discount->valid_from)) {
            throw new RuntimeException("Discount '{$discount->name}' is not yet valid.");
        }
        if ($discount->valid_until && now()->gt($discount->valid_until)) {
            throw new RuntimeException("Discount '{$discount->name}' has expired.");
        }

        // Check minimum order amount
        if ($discount->min_order_amount !== null) {
            if (bccomp($subtotal, (string) $discount->min_order_amount, 4) < 0) {
                throw new RuntimeException(
                    "Order subtotal does not meet minimum requirement of {$discount->min_order_amount} for discount '{$discount->name}'."
                );
            }
        }

        if ($discount->type === 'percentage') {
            $amount = bcmul($subtotal, bcdiv((string) $discount->value, '100', 8), 4);
        } else {
            $amount = bcdiv((string) $discount->value, '1', 4);
        }

        // Cap at max_discount_amount
        if ($discount->max_discount_amount !== null) {
            if (bccomp($amount, (string) $discount->max_discount_amount, 4) > 0) {
                $amount = bcdiv((string) $discount->max_discount_amount, '1', 4);
            }
        }

        // Cannot exceed subtotal
        if (bccomp($amount, $subtotal, 4) > 0) {
            $amount = $subtotal;
        }

        return $amount;
    }
}