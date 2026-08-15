<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_numbers')) {
            Schema::create('document_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('type');
                $table->string('date');
                $table->integer('last_number')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'type', 'date'], 'document_numbers_workspace_type_date_unique');
            });
        }

        if (! Schema::hasTable('crm_webforms')) {
            Schema::create('crm_webforms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
                $table->foreignId('stage_id')->constrained('crm_stages')->cascadeOnDelete();
                $table->foreignId('source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->json('fields')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_webforms');
        Schema::dropIfExists('document_numbers');
    }
};
