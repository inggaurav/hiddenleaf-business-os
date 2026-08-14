<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->migrateLegacyPosData();

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            if (! $this->hasIndex('warehouse_stocks', 'warehouse_stocks_warehouse_product_unique')) {
                $table->unique(['warehouse_id', 'product_id'], 'warehouse_stocks_warehouse_product_unique');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'reference_line_id')) {
                $table->unsignedBigInteger('reference_line_id')->nullable()->after('reference_id');
            }
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! $this->hasIndex('stock_movements', 'stock_movements_source_unique')) {
                $table->unique(
                    ['workspace_id', 'type', 'reference_type', 'reference_id', 'reference_line_id', 'warehouse_id', 'product_id'],
                    'stock_movements_source_unique'
                );
            }
        });

        Schema::table('transfers', function (Blueprint $table) {
            if (! $this->hasIndex('transfers', 'transfers_workspace_number_unique')) {
                $table->unique(['workspace_id', 'transfer_number'], 'transfers_workspace_number_unique');
            }
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            }
            if (! Schema::hasColumn('pos_sales', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('request_fingerprint');
            }
        });

        if ($this->hasIndex('pos_sales', 'pos_sales_idempotency_key_unique')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->dropUnique('pos_sales_idempotency_key_unique'));
        }
        Schema::table('pos_sales', function (Blueprint $table) {
            if (! $this->hasIndex('pos_sales', 'pos_sales_workspace_idempotency_unique')) {
                $table->unique(['workspace_id', 'idempotency_key'], 'pos_sales_workspace_idempotency_unique');
            }
            if (! $this->hasIndex('pos_sales', 'pos_sales_workspace_number_unique')) {
                $table->unique(['workspace_id', 'sale_number'], 'pos_sales_workspace_number_unique');
            }
        });

        Schema::table('pos_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_returns', 'refund_reference')) {
                $table->string('refund_reference')->nullable()->after('refund_method');
            }
            if (! Schema::hasColumn('pos_returns', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('refund_reference');
            }
            if (! $this->hasIndex('pos_returns', 'pos_returns_workspace_number_unique')) {
                $table->unique(['workspace_id', 'return_number'], 'pos_returns_workspace_number_unique');
            }
        });

        foreach ([
            ['pos_numbers', 'pos_numbers_workspace_date_unique'],
            ['pos_return_numbers', 'pos_return_numbers_workspace_date_unique'],
        ] as [$tableName, $indexName]) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
                if (! $this->hasIndex($tableName, $indexName)) {
                    $table->unique(['workspace_id', 'date'], $indexName);
                }
            });
        }

        if (! Schema::hasTable('pos_idempotency_keys')) {
            Schema::create('pos_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('idempotency_key', 64);
                $table->string('request_fingerprint', 64);
                $table->unsignedBigInteger('pos_sale_id')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'idempotency_key'], 'pos_idem_workspace_key_unique');
            });
        }

        // Referential integrity is added only after legacy data has been copied.
        Schema::table('billing_counters', function (Blueprint $table) {
            $table->foreign('warehouse_id', 'billing_counters_warehouse_fk')->references('id')->on('warehouses')->restrictOnDelete();
        });
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreign('billing_counter_id', 'pos_sales_counter_fk')->references('id')->on('billing_counters')->restrictOnDelete();
            $table->foreign('warehouse_id', 'pos_sales_warehouse_fk')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('customer_id', 'pos_sales_customer_fk')->references('id')->on('account_customers')->nullOnDelete();
            $table->foreign('cashier_id', 'pos_sales_cashier_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('journal_entry_id', 'pos_sales_journal_fk')->references('id')->on('journal_entries')->restrictOnDelete();
        });
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->foreign('pos_sale_id', 'pos_sale_items_sale_fk')->references('id')->on('pos_sales')->cascadeOnDelete();
            $table->foreign('product_id', 'pos_sale_items_product_fk')->references('id')->on('product_service_items')->restrictOnDelete();
        });
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->foreign('pos_sale_id', 'pos_returns_sale_fk')->references('id')->on('pos_sales')->restrictOnDelete();
            $table->foreign('processed_by', 'pos_returns_processed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id', 'pos_returns_journal_fk')->references('id')->on('journal_entries')->restrictOnDelete();
        });
        Schema::table('pos_return_items', function (Blueprint $table) {
            $table->foreign('pos_return_id', 'pos_return_items_return_fk')->references('id')->on('pos_returns')->cascadeOnDelete();
            $table->foreign('pos_sale_item_id', 'pos_return_items_sale_item_fk')->references('id')->on('pos_sale_items')->restrictOnDelete();
            $table->foreign('product_id', 'pos_return_items_product_fk')->references('id')->on('product_service_items')->restrictOnDelete();
        });
        Schema::table('pos_numbers', function (Blueprint $table) {
            $table->foreign('workspace_id', 'pos_numbers_workspace_fk')->references('id')->on('workspaces')->cascadeOnDelete();
        });
        Schema::table('pos_return_numbers', function (Blueprint $table) {
            $table->foreign('workspace_id', 'pos_return_numbers_workspace_fk')->references('id')->on('workspaces')->cascadeOnDelete();
        });
        Schema::table('pos_idempotency_keys', function (Blueprint $table) {
            $table->foreign('pos_sale_id', 'pos_idempotency_sale_fk')->references('id')->on('pos_sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Hardening constraints are intentionally not rolled back destructively.
        // Dropping them on a production downgrade could reopen duplication and
        // tenancy defects. Application rollback should deploy the preceding
        // release without deleting preserved business data.
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }

    private function migrateLegacyPosData(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasTable('pos_registers') || ! Schema::hasTable('pos_sessions')) {
            return;
        }

        DB::transaction(function () {
            $registers = DB::table('pos_registers')->orderBy('id')->get();
            foreach ($registers as $register) {
                DB::table('billing_counters')->insertOrIgnore([
                    'id' => $register->id,
                    'organization_id' => $register->organization_id,
                    'workspace_id' => $register->workspace_id,
                    'name' => $register->name,
                    'counter_number' => 'LEGACY-'.$register->id,
                    'warehouse_id' => $register->warehouse_id,
                    'is_active' => $register->is_active,
                    'created_by' => null,
                    'created_at' => $register->created_at,
                    'updated_at' => $register->updated_at,
                ]);
            }

            $sessions = DB::table('pos_sessions')->get()->keyBy('id');
            $registerMap = $registers->keyBy('id');

            foreach (DB::table('pos_orders')->orderBy('id')->get() as $order) {
                $session = $sessions->get($order->session_id);
                $register = $session ? $registerMap->get($session->register_id) : null;
                if (! $session || ! $register) {
                    throw new RuntimeException("Cannot migrate legacy POS order {$order->id}: register/session relationship is missing.");
                }

                DB::table('pos_sales')->insertOrIgnore([
                    'id' => $order->id,
                    'organization_id' => $order->organization_id,
                    'workspace_id' => $order->workspace_id,
                    'sale_number' => $order->receipt_number,
                    'billing_counter_id' => $register->id,
                    'warehouse_id' => $register->warehouse_id,
                    'customer_id' => null,
                    'cashier_id' => $order->created_by,
                    'subtotal' => $order->subtotal,
                    'tax_amount' => $order->tax_total,
                    'discount_amount' => $order->discount_total,
                    'total' => $order->grand_total,
                    'payment_method' => $order->payment_method,
                    'payment_reference' => null,
                    'status' => $order->status,
                    'notes' => trim('Migrated legacy POS order. '.($order->customer_name ? 'Customer: '.$order->customer_name.'. ' : '').($order->customer_email ? 'Email: '.$order->customer_email : '')),
                    'idempotency_key' => 'legacy-order-'.$order->workspace_id.'-'.$order->id,
                    'request_fingerprint' => hash('sha256', 'legacy-pos-order-'.$order->workspace_id.'-'.$order->id),
                    'journal_entry_id' => null,
                    'posted_at' => $order->created_at,
                    'created_by' => $order->created_by,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]);
            }

            if (Schema::hasTable('pos_order_items')) {
                foreach (DB::table('pos_order_items')->orderBy('id')->get() as $item) {
                    DB::table('pos_sale_items')->insertOrIgnore([
                        'id' => $item->id,
                        'pos_sale_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->name,
                        'sku' => $item->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => 0,
                        'tax_amount' => $item->tax_amount,
                        'discount_amount' => $item->discount_amount,
                        'line_total' => $item->line_total,
                        'type' => 'product',
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }

            if (Schema::hasTable('legacy_pos_returns')) {
                foreach (DB::table('legacy_pos_returns')->orderBy('id')->get() as $return) {
                    $sale = DB::table('pos_sales')->where('id', $return->order_id)->first();
                    if (! $sale) {
                        throw new RuntimeException("Cannot migrate legacy POS return {$return->id}: sale is missing.");
                    }
                    DB::table('pos_returns')->insertOrIgnore([
                        'id' => $return->id,
                        'organization_id' => $return->organization_id,
                        'workspace_id' => $return->workspace_id,
                        'pos_sale_id' => $return->order_id,
                        'return_number' => $return->return_number,
                        'status' => 'completed',
                        'reason' => $return->reason,
                        'refund_amount' => $return->refund_total,
                        'refund_method' => $sale->payment_method,
                        'refund_reference' => null,
                        'journal_entry_id' => null,
                        'processed_by' => $return->created_by,
                        'processed_at' => $return->created_at,
                        'created_by' => $return->created_by,
                        'created_at' => $return->created_at,
                        'updated_at' => $return->updated_at,
                    ]);
                }
            }

            if (Schema::hasTable('legacy_pos_return_items') && Schema::hasTable('pos_order_items')) {
                $legacyItems = DB::table('pos_order_items')->get()->keyBy('id');
                foreach (DB::table('legacy_pos_return_items')->orderBy('id')->get() as $item) {
                    $saleItem = $legacyItems->get($item->order_item_id);
                    if (! $saleItem) {
                        throw new RuntimeException("Cannot migrate legacy POS return item {$item->id}: order item is missing.");
                    }
                    DB::table('pos_return_items')->insertOrIgnore([
                        'id' => $item->id,
                        'pos_return_id' => $item->return_id,
                        'pos_sale_item_id' => $item->order_item_id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $item->quantity,
                        'refund_amount' => $item->refund_amount,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }
        });
    }
};
