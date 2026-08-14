<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_accounts', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('is_bank');
                $table->string('account_holder')->nullable()->after('bank_name');
                $table->string('account_number')->nullable()->after('account_holder');
                $table->string('branch_name')->nullable()->after('account_number');
                $table->string('iban')->nullable()->after('branch_name');
                $table->string('swift_code')->nullable()->after('iban');
                $table->decimal('opening_balance', 18, 2)->default(0)->after('swift_code');
                $table->index(['workspace_id', 'is_bank', 'account_number'], 'ledger_bank_account_lookup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_accounts', 'bank_name')) {
                $table->dropIndex('ledger_bank_account_lookup');
                $table->dropColumn([
                    'bank_name', 'account_holder', 'account_number', 'branch_name',
                    'iban', 'swift_code', 'opening_balance',
                ]);
            }
        });
    }
};
