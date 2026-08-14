<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers
        if (! Schema::hasTable('account_customers')) {
            Schema::create('account_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('contact')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('billing_name')->nullable();
                $table->string('billing_country')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_phone')->nullable();
                $table->string('billing_zip')->nullable();
                $table->text('billing_address')->nullable();
                $table->string('shipping_name')->nullable();
                $table->string('shipping_country')->nullable();
                $table->string('shipping_state')->nullable();
                $table->string('shipping_city')->nullable();
                $table->string('shipping_phone')->nullable();
                $table->string('shipping_zip')->nullable();
                $table->text('shipping_address')->nullable();
                $table->decimal('balance', 18, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'email']);
            });
        }

        // Vendors
        if (! Schema::hasTable('account_vendors')) {
            Schema::create('account_vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('contact')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('billing_name')->nullable();
                $table->string('billing_country')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_phone')->nullable();
                $table->string('billing_zip')->nullable();
                $table->text('billing_address')->nullable();
                $table->string('shipping_name')->nullable();
                $table->string('shipping_country')->nullable();
                $table->string('shipping_state')->nullable();
                $table->string('shipping_city')->nullable();
                $table->string('shipping_phone')->nullable();
                $table->string('shipping_zip')->nullable();
                $table->text('shipping_address')->nullable();
                $table->decimal('balance', 18, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'email']);
            });
        }

        // Customer Payments
        if (! Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->foreignId('account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
                $table->decimal('amount', 18, 2);
                $table->date('payment_date');
                $table->string('payment_method')->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('receipt')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'customer_id']);
                $table->index(['workspace_id', 'invoice_id']);
            });
        }

        // Vendor Payments
        if (! Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('purchase_invoice_id')->nullable();
                $table->foreignId('account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
                $table->decimal('amount', 18, 2);
                $table->date('payment_date');
                $table->string('payment_method')->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('receipt')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'vendor_id']);
                $table->index(['workspace_id', 'purchase_invoice_id']);
            });
        }

        // Revenue Transactions
        if (! Schema::hasTable('account_revenues')) {
            Schema::create('account_revenues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->foreignId('account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->date('date');
                $table->string('payment_method')->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('receipt')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'date']);
            });
        }

        // Expense Transactions
        if (! Schema::hasTable('account_expenses')) {
            Schema::create('account_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->foreignId('account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->date('date');
                $table->string('payment_method')->default('bank_transfer');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('receipt')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'date']);
            });
        }

        // Credit Notes
        if (! Schema::hasTable('account_credit_notes')) {
            Schema::create('account_credit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->date('date');
                $table->text('description')->nullable();
                $table->string('status')->default('applied');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'invoice_id']);
            });
        }

        // Debit Notes
        if (! Schema::hasTable('account_debit_notes')) {
            Schema::create('account_debit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('purchase_invoice_id')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->date('date');
                $table->text('description')->nullable();
                $table->string('status')->default('applied');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'purchase_invoice_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_debit_notes');
        Schema::dropIfExists('account_credit_notes');
        Schema::dropIfExists('account_expenses');
        Schema::dropIfExists('account_revenues');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('account_vendors');
        Schema::dropIfExists('account_customers');
    }
};
