<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old pos tables if any
        Schema::dropIfExists('pos_return_items');
        Schema::dropIfExists('pos_returns');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_registers');

        Schema::create('billing_counters', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('name');
            $table->string('counter_number');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('sale_number');
            $table->unsignedBigInteger('billing_counter_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('cashier_id');
            $table->decimal('subtotal', 15, 4);
            $table->decimal('tax_amount', 15, 4);
            $table->decimal('discount_amount', 15, 4);
            $table->decimal('total', 15, 4);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('created_by');
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_sale_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->enum('type', ['product', 'service']);
            $table->timestamps();
        });

        Schema::create('pos_returns', function (Blueprint $table) {
            $this->tenant($table);
            $table->unsignedBigInteger('pos_sale_id');
            $table->string('return_number');
            $table->enum('status', ['draft', 'approved', 'completed', 'cancelled']);
            $table->text('reason')->nullable();
            $table->decimal('refund_amount', 15, 4);
            $table->string('refund_method')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('created_by');
        });

        Schema::create('pos_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_return_id');
            $table->unsignedBigInteger('pos_sale_item_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('refund_amount', 15, 4);
            $table->timestamps();
        });

        Schema::create('pos_discounts', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 4);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
        });

        Schema::create('pos_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('date');
            $table->integer('last_number')->default(0);
            $table->timestamps();
        });
        
        Schema::create('pos_return_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('date');
            $table->integer('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = ['pos_return_numbers', 'pos_numbers', 'pos_discounts', 'pos_return_items', 'pos_returns', 'pos_sale_items', 'pos_sales', 'billing_counters'];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $t->timestamps();
    }
};
