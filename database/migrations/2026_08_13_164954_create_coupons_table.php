<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('type')->default('percentage');
            $table->integer('limit')->nullable();
            $table->integer('limit_per_user')->nullable();
            $table->integer('used')->default(0);
            $table->decimal('minimum_spend', 10, 2)->nullable();
            $table->decimal('maximum_spend', 10, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('included_module')->nullable();
            $table->string('excluded_module')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
