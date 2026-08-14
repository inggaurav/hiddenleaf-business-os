<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Upgrade safety: never drop the legacy POS domain during an upgrade.
         * The old schema used pos_orders/pos_sessions/pos_registers and also
         * used the names pos_returns/pos_return_items. Preserve the non-
         * conflicting tables and rename only the conflicting legacy return
         * tables before creating the V1 POS schema. A later migration copies
         * legacy rows into the new canonical tables.
         */
        if (Schema::hasTable('pos_returns') && Schema::hasColumn('pos_returns', 'order_id') && ! Schema::hasColumn('pos_returns', 'pos_sale_id')) {
            if (! Schema::hasTable('legacy_pos_returns')) {
                Schema::rename('pos_returns', 'legacy_pos_returns');
            }
        }
        if (Schema::hasTable('pos_return_items') && Schema::hasColumn('pos_return_items', 'return_id') && ! Schema::hasColumn('pos_return_items', 'pos_return_id')) {
            if (! Schema::hasTable('legacy_pos_return_items')) {
                Schema::rename('pos_return_items', 'legacy_pos_return_items');
            }
        }

        if (! Schema::hasTable('billing_counters')) {
            Schema::create('billing_counters', function (Blueprint $table) {
                $this->tenant($table);
                $table->string('name');
                $table->string('counter_number');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('pos_sales')) {
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
                $table->string('idempotency_key');
                $table->string('request_fingerprint', 64)->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unique(['workspace_id', 'sale_number'], 'pos_sales_workspace_number_unique');
                $table->unique(['workspace_id', 'idempotency_key'], 'pos_sales_workspace_idempotency_unique');
            });
        }

        if (! Schema::hasTable('pos_sale_items')) {
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
        }

        if (! Schema::hasTable('pos_returns')) {
            Schema::create('pos_returns', function (Blueprint $table) {
                $this->tenant($table);
                $table->unsignedBigInteger('pos_sale_id');
                $table->string('return_number');
                $table->enum('status', ['draft', 'approved', 'completed', 'cancelled']);
                $table->text('reason')->nullable();
                $table->decimal('refund_amount', 15, 4);
                $table->string('refund_method')->nullable();
                $table->string('refund_reference')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unique(['workspace_id', 'return_number'], 'pos_returns_workspace_number_unique');
            });
        }

        if (! Schema::hasTable('pos_return_items')) {
            Schema::create('pos_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_return_id');
                $table->unsignedBigInteger('pos_sale_item_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 15, 4);
                $table->decimal('refund_amount', 15, 4);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_discounts')) {
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
        }

        if (! Schema::hasTable('pos_numbers')) {
            Schema::create('pos_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('date');
                $table->integer('last_number')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'date'], 'pos_numbers_workspace_date_unique');
            });
        }

        if (! Schema::hasTable('pos_return_numbers')) {
            Schema::create('pos_return_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('date');
                $table->integer('last_number')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'date'], 'pos_return_numbers_workspace_date_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['pos_return_numbers', 'pos_numbers', 'pos_discounts', 'pos_return_items', 'pos_returns', 'pos_sale_items', 'pos_sales', 'billing_counters'] as $table) {
            Schema::dropIfExists($table);
        }

        // Deliberately do not drop or rename preserved legacy POS tables here.
    }

    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $t->timestamps();
    }
};
