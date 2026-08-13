<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_service_items', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('sku');
            $table->decimal('reorder_level', 15, 2)->default(0)->after('purchase_price');
            $table->unique(['workspace_id', 'barcode']);
        });

        Schema::create('product_service_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 32);
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('product_service_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 8, 4);
            $table->boolean('is_compound')->default(false);
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('product_service_item_taxes', function (Blueprint $table) {
            $table->foreignId('product_service_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_service_tax_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_service_item_id', 'product_service_tax_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('product_service_items')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('quantity', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->nullableMorphs('reference');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_service_item_taxes');
        Schema::dropIfExists('product_service_taxes');
        Schema::dropIfExists('product_service_units');
        Schema::table('product_service_items', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'barcode']);
            $table->dropColumn(['barcode', 'reorder_level']);
        });
    }
};
