<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_histories', function (Blueprint $table) {
            $table->id();
            $table->string('from_version');
            $table->string('to_version');
            $table->string('channel');
            $table->string('status')->index();
            $table->jsonb('manifest');
            $table->text('backup_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_histories');
    }
};
