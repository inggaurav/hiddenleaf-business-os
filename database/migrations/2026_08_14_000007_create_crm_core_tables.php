<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->boolean('is_default')->default(false);
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('crm_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('position')->default(0);
            $t->decimal('probability', 5, 2)->default(0);
            $t->boolean('is_closed')->default(false);
            $t->string('outcome')->nullable();
            $t->timestamps();
            $t->unique(['pipeline_id', 'name']);
        });
        Schema::create('crm_sources', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('crm_labels', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('color', 20)->default('#64748b');
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('crm_leads', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $t->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $t->foreignId('source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('company')->nullable();
            $t->decimal('estimated_value', 18, 2)->default(0);
            $t->string('status')->default('open');
            $t->timestamp('converted_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('crm_deals', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $t->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $t->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name');
            $t->decimal('value', 18, 2)->default(0);
            $t->date('expected_close_on')->nullable();
            $t->string('status')->default('open');
            $t->timestamp('closed_at')->nullable();
            $t->text('loss_reason')->nullable();
        });
        Schema::create('crm_labelables', function (Blueprint $t) {
            $t->foreignId('label_id')->constrained('crm_labels')->cascadeOnDelete();
            $t->morphs('labelable');
            $t->primary(['label_id', 'labelable_type', 'labelable_id']);
        });
        Schema::create('crm_activities', function (Blueprint $t) {
            $this->tenant($t);
            $t->nullableMorphs('subject');
            $t->string('type');
            $t->string('title');
            $t->dateTime('due_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('crm_notes', function (Blueprint $t) {
            $this->tenant($t);
            $t->morphs('subject');
            $t->text('body');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['crm_notes', 'crm_activities', 'crm_labelables', 'crm_deals', 'crm_leads', 'crm_labels', 'crm_sources', 'crm_stages', 'crm_pipelines'] as $table) {
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
