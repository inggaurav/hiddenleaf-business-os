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
            if (! Schema::hasColumn('customer_payments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('receipt');
                $table->unique(['workspace_id', 'idempotency_key'], 'cp_ws_idempotency_unique');
            }
            if (! Schema::hasColumn('customer_payments', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            }

            $table->foreign('customer_id')->references('id')->on('account_customers')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('sales_invoices')->nullOnDelete();
        });

        // Vendor Payments foreign keys + idempotency
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('receipt');
                $table->unique(['workspace_id', 'idempotency_key'], 'vp_ws_idempotency_unique');
            }
            if (! Schema::hasColumn('vendor_payments', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            }

            $table->foreign('vendor_id')->references('id')->on('account_vendors')->restrictOnDelete();
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->nullOnDelete();
        });

        // Revenue foreign keys
        Schema::table('account_revenues', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('account_customers')->nullOnDelete();
        });

        // Expense foreign keys
        Schema::table('account_expenses', function (Blueprint $table) {
            $table->foreign('vendor_id')->references('id')->on('account_vendors')->nullOnDelete();
        });

        // Credit Note foreign keys
        Schema::table('account_credit_notes', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('sales_invoices')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('account_customers')->restrictOnDelete();
        });

        // Debit Note foreign keys
        Schema::table('account_debit_notes', function (Blueprint $table) {
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('account_vendors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_debit_notes', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']);
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('account_credit_notes', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('account_expenses', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('account_revenues', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['purchase_invoice_id']);
            if (Schema::hasColumn('vendor_payments', 'request_fingerprint')) {
                $table->dropColumn('request_fingerprint');
            }
            if (Schema::hasColumn('vendor_payments', 'idempotency_key')) {
                $table->dropUnique('vp_ws_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['invoice_id']);
            if (Schema::hasColumn('customer_payments', 'request_fingerprint')) {
                $table->dropColumn('request_fingerprint');
            }
            if (Schema::hasColumn('customer_payments', 'idempotency_key')) {
                $table->dropUnique('cp_ws_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
