<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ch_messages', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable();
            $table->index(['workspace_id', 'to_id', 'seen']);
        });

        Schema::table('ch_favorites', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id', 'favorite_id']);
        });

        Schema::table('ch_pinned', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id', 'pinned_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ch_pinned', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'user_id', 'pinned_id']);
            $table->dropConstrainedForeignId('workspace_id');
        });
        Schema::table('ch_favorites', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'user_id', 'favorite_id']);
            $table->dropConstrainedForeignId('workspace_id');
        });
        Schema::table('ch_messages', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'to_id', 'seen']);
            $table->dropColumn('pinned_at');
        });
    }
};
