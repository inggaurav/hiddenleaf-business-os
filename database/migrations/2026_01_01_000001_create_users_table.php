<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user'); // super_admin, company_admin, member
            $table->string('type')->default('company');
            $table->unsignedBigInteger('active_plan')->nullable();
            $table->date('plan_expire_date')->nullable();
            $table->integer('is_trial_done')->default(0);
            $table->integer('user_counter')->default(0);
            $table->bigInteger('storage_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->string('lang')->default('en');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
