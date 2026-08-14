<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_service_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_service_categories', 'color')) {
                $table->string('color', 16)->default('#3b82f6')->after('name');
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'code')) {
                $table->string('code', 32)->nullable()->after('name');
            }
            if (! Schema::hasColumn('warehouses', 'phone')) {
                $table->string('phone', 64)->nullable()->after('city_zip');
            }
            if (! Schema::hasColumn('warehouses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('phone');
            }
        });

        Schema::table('transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('transfers', 'transfer_number')) {
                $table->string('transfer_number', 64)->nullable()->after('id');
            }
            if (! Schema::hasColumn('transfers', 'status')) {
                $table->string('status', 32)->default('completed')->after('date');
            }
            if (! Schema::hasColumn('transfers', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('transfers', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('transfers', 'processed_by')) {
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete()->after('processed_at');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'direction')) {
                $table->smallInteger('direction')->default(1)->after('quantity');
            }
            if (! Schema::hasColumn('stock_movements', 'unit_cost')) {
                $table->decimal('unit_cost', 18, 4)->nullable()->after('direction');
            }
            if (! Schema::hasColumn('stock_movements', 'total_cost')) {
                $table->decimal('total_cost', 18, 2)->nullable()->after('unit_cost');
            }
            if (! Schema::hasColumn('stock_movements', 'source_warehouse_id')) {
                $table->unsignedBigInteger('source_warehouse_id')->nullable()->after('reference_id');
            }
            if (! Schema::hasColumn('stock_movements', 'destination_warehouse_id')) {
                $table->unsignedBigInteger('destination_warehouse_id')->nullable()->after('source_warehouse_id');
            }
            if (! Schema::hasColumn('stock_movements', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            $table->index(['workspace_id', 'type', 'created_at'], 'sm_ws_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('sm_ws_type_created_idx');
            $table->dropColumn(['direction', 'unit_cost', 'total_cost', 'source_warehouse_id', 'destination_warehouse_id', 'notes']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'processed_by')) {
                $table->dropForeign(['processed_by']);
            }
            $table->dropColumn(['transfer_number', 'status', 'notes', 'processed_at', 'processed_by']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['code', 'phone', 'is_active']);
        });

        Schema::table('product_service_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
