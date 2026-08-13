<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('product');
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'type', 'name']);
        });

        Schema::create('product_service_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('product');
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('product_service_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'sku']);
            $table->index(['workspace_id', 'type', 'is_active']);
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('product_service_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('product_service_items');
        Schema::dropIfExists('product_service_categories');
    }
};
