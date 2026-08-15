<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Persistent Conversations Table
        if (! Schema::hasTable('mrfox_conversations')) {
            Schema::create('mrfox_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title', 255)->default('New Executive Session');
                $table->string('active_page', 100)->default('Dashboard');
                $table->boolean('is_archived')->default(false);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->timestamps();

                $table->index(['workspace_id', 'user_id', 'is_archived']);
                $table->index(['organization_id', 'workspace_id']);
            });
        }

        // 2. Persistent Messages Table
        if (! Schema::hasTable('mrfox_messages')) {
            Schema::create('mrfox_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('mrfox_conversations')->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('role', ['user', 'assistant', 'system', 'tool']);
                $table->longText('content');
                $table->json('tool_calls')->nullable();
                $table->json('tool_results')->nullable();
                $table->json('evidence')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('model', 100)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['workspace_id', 'created_at']);
            });
        }

        // 3. Add payload_hash and error_trace to mrfox_action_proposals if not present
        if (Schema::hasTable('mrfox_action_proposals')) {
            Schema::table('mrfox_action_proposals', function (Blueprint $table) {
                if (! Schema::hasColumn('mrfox_action_proposals', 'payload_hash')) {
                    $table->string('payload_hash', 64)->nullable()->after('payload');
                }
                if (! Schema::hasColumn('mrfox_action_proposals', 'error_trace')) {
                    $table->text('error_trace')->nullable()->after('error');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mrfox_messages');
        Schema::dropIfExists('mrfox_conversations');
    }
};
