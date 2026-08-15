<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->json('condition_config')->nullable();
            $table->json('action_config');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'enabled', 'trigger_type']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            $table->string('trigger_event');
            $table->json('trigger_payload')->nullable();
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('trace_id')->nullable();
            $table->unsignedSmallInteger('depth')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('automation_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_index')->default(0);
            $table->string('action_name');
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approval_proposal_id')->nullable()->constrained('mrfox_action_proposals')->nullOnDelete();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'step_index']);
        });

        Schema::create('mr_fox_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('brand_profile_id')->nullable()->constrained('mrfox_brand_profiles')->nullOnDelete();
            $table->string('name');
            $table->text('objective');
            $table->string('status')->default('draft');
            $table->string('risk_level')->default('governed');
            $table->json('plan_json')->nullable();
            $table->json('allowed_tools')->nullable();
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->text('progress_summary')->nullable();
            $table->unsignedInteger('max_steps')->default(15);
            $table->unsignedInteger('execution_budget_tokens')->default(50000);
            $table->unsignedInteger('used_tokens')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('mr_fox_mission_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('mr_fox_missions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('tool_name');
            $table->json('input_params')->nullable();
            $table->json('output_data')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approval_proposal_id')->nullable()->constrained('mrfox_action_proposals')->nullOnDelete();
            $table->json('evidence')->nullable();
            $table->text('observation')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_fox_mission_steps');
        Schema::dropIfExists('mr_fox_missions');
        Schema::dropIfExists('automation_run_steps');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
