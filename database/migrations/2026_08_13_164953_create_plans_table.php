<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('package_price_monthly', 10, 2)->default(0);
            $table->decimal('package_price_yearly', 10, 2)->default(0);
            $table->decimal('price_per_user_monthly', 10, 2)->default(0);
            $table->decimal('price_per_user_yearly', 10, 2)->default(0);
            $table->decimal('price_per_storage_monthly', 10, 2)->default(0);
            $table->decimal('price_per_storage_yearly', 10, 2)->default(0);
            $table->integer('number_of_users')->default(1);
            $table->bigInteger('storage_limit')->default(0);
            $table->integer('workspace_limit')->default(1);
            $table->jsonb('modules')->nullable();
            $table->boolean('trial')->default(false);
            $table->integer('trial_days')->default(0);
            $table->boolean('free_plan')->default(false);
            $table->boolean('status')->default(true);
            $table->boolean('custom_plan')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
