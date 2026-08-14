<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Payments foreign keys + idempotency
        Schema::table('customer_payments', function (Blueprint $table) {
            // Add idempotency_key column
            if (! Schema::hasColumn('customer_payments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('receipt');
                $table->unique(['workspace_id', 'idempotency_key'], 'cp_ws_idempotency_unique');
            }
        });

        // Add FK constraints separately to handle potential failures gracefully
        $this->addForeignKeySafe('customer_payments', 'customer_id', 'account_customers', 'id', 'restrict');
        $this->addForeignKeySafe('customer_payments', 'invoice_id', 'sales_invoices', 'id', 'nullOnDelete');

        // Vendor Payments foreign keys + idempotency
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('receipt');
                $table->unique(['workspace_id', 'idempotency_key'], 'vp_ws_idempotency_unique');
            }
        });

        $this->addForeignKeySafe('vendor_payments', 'vendor_id', 'account_vendors', 'id', 'restrict');
        $this->addForeignKeySafe('vendor_payments', 'purchase_invoice_id', 'purchase_invoices', 'id', 'nullOnDelete');

        // Revenue foreign keys
        $this->addForeignKeySafe('account_revenues', 'customer_id', 'account_customers', 'id', 'nullOnDelete');

        // Expense foreign keys
        $this->addForeignKeySafe('account_expenses', 'vendor_id', 'account_vendors', 'id', 'nullOnDelete');

        // Credit Note foreign keys
        $this->addForeignKeySafe('account_credit_notes', 'invoice_id', 'sales_invoices', 'id', 'nullOnDelete');
        $this->addForeignKeySafe('account_credit_notes', 'customer_id', 'account_customers', 'id', 'restrict');

        // Debit Note foreign keys
        $this->addForeignKeySafe('account_debit_notes', 'purchase_invoice_id', 'purchase_invoices', 'id', 'nullOnDelete');
        $this->addForeignKeySafe('account_debit_notes', 'vendor_id', 'account_vendors', 'id', 'restrict');
    }

    public function down(): void
    {
        $this->dropForeignKeySafe('customer_payments', 'customer_id');
        $this->dropForeignKeySafe('customer_payments', 'invoice_id');
        $this->dropForeignKeySafe('vendor_payments', 'vendor_id');
        $this->dropForeignKeySafe('vendor_payments', 'purchase_invoice_id');
        $this->dropForeignKeySafe('account_revenues', 'customer_id');
        $this->dropForeignKeySafe('account_expenses', 'vendor_id');
        $this->dropForeignKeySafe('account_credit_notes', 'invoice_id');
        $this->dropForeignKeySafe('account_credit_notes', 'customer_id');
        $this->dropForeignKeySafe('account_debit_notes', 'purchase_invoice_id');
        $this->dropForeignKeySafe('account_debit_notes', 'vendor_id');

        Schema::table('customer_payments', function (Blueprint $table) {
            if (Schema::hasColumn('customer_payments', 'idempotency_key')) {
                $table->dropUnique('cp_ws_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payments', 'idempotency_key')) {
                $table->dropUnique('vp_ws_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }

    private function addForeignKeySafe(string $table, string $column, string $referencedTable, string $referencedColumn, string $onDelete): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $fk = $blueprint->foreign($column)->references($referencedColumn)->on($referencedTable);
                match ($onDelete) {
                    'restrict' => $fk->restrictOnDelete(),
                    'nullOnDelete' => $fk->nullOnDelete(),
                    'cascade' => $fk->cascadeOnDelete(),
                    default => $fk,
                };
            });
        } catch (\Throwable $e) {
            // FK may already exist or referenced table may not exist yet
            report($e);
        }
    }

    private function dropForeignKeySafe(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // FK may not exist
        }
    }
};
