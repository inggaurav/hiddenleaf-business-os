<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_revenues', function (Blueprint $table) {
            $table->foreign('category_id', 'account_revenues_category_fk')
                ->references('id')
                ->on('account_transaction_categories')
                ->restrictOnDelete();
        });

        Schema::table('account_expenses', function (Blueprint $table) {
            $table->foreign('category_id', 'account_expenses_category_fk')
                ->references('id')
                ->on('account_transaction_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_expenses', function (Blueprint $table) {
            $table->dropForeign('account_expenses_category_fk');
        });
        Schema::table('account_revenues', function (Blueprint $table) {
            $table->dropForeign('account_revenues_category_fk');
        });
    }
};
