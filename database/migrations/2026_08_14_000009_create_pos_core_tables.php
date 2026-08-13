<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_registers', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('pos_sessions', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('register_id')->constrained('pos_registers')->cascadeOnDelete();
            $t->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('opening_cash', 18, 2);
            $t->decimal('closing_cash', 18, 2)->nullable();
            $t->decimal('expected_cash', 18, 2)->nullable();
            $t->decimal('variance', 18, 2)->nullable();
            $t->string('status')->default('open');
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
        });
        Schema::create('pos_orders', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('session_id')->constrained('pos_sessions')->restrictOnDelete();
            $t->string('receipt_number');
            $t->string('customer_name')->nullable();
            $t->string('customer_email')->nullable();
            $t->decimal('subtotal', 18, 2);
            $t->decimal('tax_total', 18, 2);
            $t->decimal('discount_total', 18, 2);
            $t->decimal('grand_total', 18, 2);
            $t->decimal('paid_amount', 18, 2);
            $t->decimal('change_amount', 18, 2);
            $t->string('payment_method');
            $t->string('status')->default('completed');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unique(['workspace_id', 'receipt_number']);
        });
        Schema::create('pos_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('product_service_items')->restrictOnDelete();
            $t->string('name');
            $t->string('sku')->nullable();
            $t->decimal('quantity', 15, 2);
            $t->decimal('unit_price', 18, 2);
            $t->decimal('tax_amount', 18, 2);
            $t->decimal('discount_amount', 18, 2);
            $t->decimal('line_total', 18, 2);
            $t->timestamps();
        });
        Schema::create('pos_returns', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('order_id')->constrained('pos_orders')->restrictOnDelete();
            $t->string('return_number');
            $t->decimal('refund_total', 18, 2);
            $t->text('reason');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unique(['workspace_id', 'return_number']);
        });
        Schema::create('pos_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('pos_returns')->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained('pos_order_items')->restrictOnDelete();
            $t->decimal('quantity', 15, 2);
            $t->decimal('refund_amount', 18, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pos_return_items', 'pos_returns', 'pos_order_items', 'pos_orders', 'pos_sessions', 'pos_registers'] as $table) {
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
