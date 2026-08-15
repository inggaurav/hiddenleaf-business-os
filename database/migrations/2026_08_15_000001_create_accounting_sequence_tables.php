<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_numbers')) {
            Schema::create('journal_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('date');
                $table->integer('last_number')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'date'], 'journal_numbers_workspace_date_unique');
            });
        }

        if (! Schema::hasTable('bank_transfer_numbers')) {
            Schema::create('bank_transfer_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('date');
                $table->integer('last_number')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'date'], 'bank_transfer_numbers_workspace_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_numbers');
        Schema::dropIfExists('journal_numbers');
    }
};
