<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical inventory precision migration.
     *
     * Uniqueness/idempotency constraints are now owned by
     * 2026_08_14_140000_harden_inventory_and_pos_integrity.php so fresh installs
     * and upgrades from installations that already ran this migration cannot
     * attempt to create the same constraints twice.
     */
    public function up(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('balance_after', 15, 4)->change();
        });
    }

    public function down(): void
    {
        // Keep current decimal precision on rollback. Narrowing historical stock
        // quantities would be destructive for fractional inventory.
    }
};
