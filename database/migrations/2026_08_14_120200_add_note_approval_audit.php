<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_credit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('account_credit_notes', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('journal_entry_id');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('account_debit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('account_debit_notes', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('journal_entry_id');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        foreach (['account_credit_notes', 'account_debit_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'approved_by')) {
                    $table->dropConstrainedForeignId('approved_by');
                }
                if (Schema::hasColumn($tableName, 'journal_entry_id')) {
                    $table->dropConstrainedForeignId('journal_entry_id');
                }
                if (Schema::hasColumn($tableName, 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
            });
        }
    }
};
