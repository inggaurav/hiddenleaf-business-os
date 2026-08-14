<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_transaction_categories')) {
            Schema::create('account_transaction_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('type', 16); // revenue|expense
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'type', 'name'], 'account_tx_category_unique');
                $table->index(['organization_id', 'workspace_id', 'type']);
            });
        }

        Schema::table('account_revenues', function (Blueprint $table) {
            if (! Schema::hasColumn('account_revenues', 'status')) {
                $table->string('status', 24)->default('posted')->after('description');
            }
            if (! Schema::hasColumn('account_revenues', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('account_revenues', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('approved_by');
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('account_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('account_expenses', 'status')) {
                $table->string('status', 24)->default('posted')->after('description');
            }
            if (! Schema::hasColumn('account_expenses', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('account_expenses', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('approved_by');
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'status')) {
                $table->string('status', 24)->default('posted')->after('request_fingerprint');
            }
            if (! Schema::hasColumn('customer_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('status');
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payments', 'status')) {
                $table->string('status', 24)->default('posted')->after('request_fingerprint');
            }
            if (! Schema::hasColumn('vendor_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('status');
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_lines', 'is_reconciled')) {
                $table->boolean('is_reconciled')->default(false)->after('credit');
                $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
                $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('account_bank_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('account_bank_transfers', 'status')) {
                $table->string('status', 24)->default('posted')->after('reference');
            }
            if (! Schema::hasColumn('account_bank_transfers', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('status');
                $table->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_bank_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('account_bank_transfers', 'processed_by')) {
                $table->dropConstrainedForeignId('processed_by');
            }
            foreach (['processed_at', 'status'] as $column) {
                if (Schema::hasColumn('account_bank_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            if (Schema::hasColumn('journal_lines', 'reconciled_by')) {
                $table->dropConstrainedForeignId('reconciled_by');
            }
            foreach (['reconciled_at', 'is_reconciled'] as $column) {
                if (Schema::hasColumn('journal_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        foreach (['customer_payments', 'vendor_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'voided_by')) {
                    $table->dropConstrainedForeignId('voided_by');
                }
                foreach (['voided_at', 'status'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        foreach (['account_revenues', 'account_expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['approved_by', 'posted_by'] as $fk) {
                    if (Schema::hasColumn($tableName, $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }
                foreach (['posted_at', 'approved_at', 'status'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('account_transaction_categories');
    }
};
