<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ch_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_id');
            $table->unsignedBigInteger('to_id');
            $table->text('body')->nullable();
            $table->string('attachment')->nullable();
            $table->boolean('seen')->default(false);
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ch_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('favorite_id');
            $table->timestamps();
        });

        Schema::create('ch_pinned', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pinned_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ch_pinned');
        Schema::dropIfExists('ch_favorites');
        Schema::dropIfExists('ch_messages');
    }
};
