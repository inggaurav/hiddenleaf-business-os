<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrfox_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('conversation_id')->nullable()->index();
            $table->string('tool_name')->index();
            $table->json('payload');
            $table->text('human_summary');
            $table->string('risk_level', 20)->default('HIGH');
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected, executed, failed, expired
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        Schema::create('mrfox_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('conversation_id')->nullable()->index();
            $table->string('tool_name')->index();
            $table->json('input_payload')->nullable();
            $table->text('output_summary')->nullable();
            $table->string('risk_level', 20)->default('READ');
            $table->string('execution_status', 30)->default('success');
            $table->integer('duration_ms')->default(0);
            $table->string('provider', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('mrfox_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50)->index();
            $table->string('model', 50);
            $table->string('capability', 50)->default('chat');
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrfox_usage_records');
        Schema::dropIfExists('mrfox_audit_logs');
        Schema::dropIfExists('mrfox_action_proposals');
    }
};
