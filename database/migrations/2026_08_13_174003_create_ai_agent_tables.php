<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('New Chat');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_agent_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_chat_messages');
        Schema::dropIfExists('ai_agent_chat_sessions');
    }
};
