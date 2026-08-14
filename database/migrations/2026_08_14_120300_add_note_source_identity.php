<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_credit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('account_credit_notes', 'source_type')) {
                $table->string('source_type', 64)->nullable()->after('description');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->unique(['workspace_id', 'source_type', 'source_id'], 'credit_note_source_unique');
            }
        });

        Schema::table('account_debit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('account_debit_notes', 'source_type')) {
                $table->string('source_type', 64)->nullable()->after('description');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->unique(['workspace_id', 'source_type', 'source_id'], 'debit_note_source_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_credit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('account_credit_notes', 'source_type')) {
                $table->dropUnique('credit_note_source_unique');
                $table->dropColumn(['source_type', 'source_id']);
            }
        });
        Schema::table('account_debit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('account_debit_notes', 'source_type')) {
                $table->dropUnique('debit_note_source_unique');
                $table->dropColumn(['source_type', 'source_id']);
            }
        });
    }
};
