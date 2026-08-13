<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskly_projects', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->text('description')->nullable();
            $t->date('starts_on')->nullable();
            $t->date('due_on')->nullable();
            $t->decimal('budget', 18, 2)->default(0);
            $t->string('status')->default('active');
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('taskly_project_members', function (Blueprint $t) {
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('hourly_rate', 12, 2)->default(0);
            $t->string('role')->default('member');
            $t->primary(['project_id', 'user_id']);
        });
        Schema::create('taskly_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_complete')->default(false);
            $t->timestamps();
            $t->unique(['project_id', 'name']);
        });
        Schema::create('taskly_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->string('name');
            $t->date('due_on')->nullable();
            $t->string('status')->default('open');
            $t->timestamps();
        });
        Schema::create('taskly_tasks', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->foreignId('stage_id')->constrained('taskly_stages')->restrictOnDelete();
            $t->foreignId('milestone_id')->nullable()->constrained('taskly_milestones')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('priority')->default('medium');
            $t->date('due_on')->nullable();
            $t->decimal('estimated_hours', 8, 2)->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('taskly_timesheets', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained('taskly_tasks')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('work_date');
            $t->decimal('hours', 8, 2);
            $t->text('description')->nullable();
            $t->string('status')->default('submitted');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
        });
        Schema::create('taskly_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('taskly_tasks')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('taskly_issues', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained('taskly_tasks')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('severity')->default('medium');
            $t->string('status')->default('open');
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('taskly_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('taskly_tasks')->cascadeOnDelete();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('disk');
            $t->string('path');
            $t->string('name');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['taskly_attachments', 'taskly_issues', 'taskly_comments', 'taskly_timesheets', 'taskly_tasks', 'taskly_milestones', 'taskly_stages', 'taskly_project_members', 'taskly_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $t->timestamps();
    }
};
