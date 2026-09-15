<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notices')) {
            Schema::create('notices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->longText('description')->nullable();
                $table->json('attachments')->nullable();
                $table->date('start_date');
                $table->date('expiry_date')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->enum('priority', ['normal', 'urgent', 'critical'])->default('normal');
                $table->boolean('require_acknowledgment')->default(false);
                $table->enum('target_type', ['all', 'department', 'role', 'specific_users'])->default('all');
                $table->boolean('allow_comments')->default(false);
                $table->enum('status', ['draft', 'published', 'deactivated'])->default('draft');
                $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['workspace_id', 'is_pinned']);
            });
        }

        if (!Schema::hasTable('notice_targets')) {
            Schema::create('notice_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
                $table->string('target_type');
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notice_reads')) {
            Schema::create('notice_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();

                $table->unique(['notice_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('notice_comments')) {
            Schema::create('notice_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('notice_comments')->cascadeOnDelete();
                $table->text('comment');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_comments');
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notice_targets');
        Schema::dropIfExists('notices');
    }
};
