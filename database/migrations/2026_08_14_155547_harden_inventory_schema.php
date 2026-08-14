<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change quantity columns to DECIMAL(15,4)
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('balance_after', 15, 4)->change();
            
            // Add reference_line_id for idempotency
            $table->unsignedBigInteger('reference_line_id')->nullable()->after('reference_id');
            
            // The unique constraint for idempotency
            $table->unique([
                'reference_type', 'reference_id', 'reference_line_id', 
                'type', 'warehouse_id', 'product_id'
            ], 'stock_movements_idempotency_unique');
        });

        Schema::table('transfers', function (Blueprint $table) {
            // Check if transfer_number exists
            if (!Schema::hasColumn('transfers', 'transfer_number')) {
                $table->string('transfer_number')->after('workspace_id')->nullable();
            }
        });
        
        // Add constraint separately to avoid failing if column existed
        Schema::table('transfers', function (Blueprint $table) {
            $table->unique(['workspace_id', 'transfer_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'transfer_number']);
        });
        
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_idempotency_unique');
            $table->dropColumn('reference_line_id');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'product_id']);
        });
    }
};
