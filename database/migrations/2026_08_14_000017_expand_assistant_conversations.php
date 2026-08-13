<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_chat_sessions', function (Blueprint $table) {
            $table->string('provider')->default('local');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->index(['workspace_id', 'user_id', 'archived_at']);
        });
        Schema::table('ai_agent_chat_messages', function (Blueprint $table) {
            $table->string('provider')->nullable();
            $table->jsonb('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['provider', 'metadata']);
        });
        Schema::table('ai_agent_chat_sessions', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'user_id', 'archived_at']);
            $table->dropColumn(['provider', 'metadata', 'archived_at']);
        });
    }
};
