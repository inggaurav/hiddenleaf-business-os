<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_bank_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('account_bank_transfers', 'transfer_number')) {
                $table->string('transfer_number')->nullable()->after('id');
                $table->decimal('transfer_charges', 18, 2)->default(0)->after('amount');
                $table->text('description')->nullable()->after('reference');
                $table->unique(['workspace_id', 'transfer_number'], 'account_transfer_number_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_bank_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('account_bank_transfers', 'transfer_number')) {
                $table->dropUnique('account_transfer_number_unique');
                $table->dropColumn(['transfer_number', 'transfer_charges', 'description']);
            }
        });
    }
};
