<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Brand Profiles Table
        if (! Schema::hasTable('mrfox_brand_profiles')) {
            Schema::create('mrfox_brand_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 150);
                $table->string('industry', 100)->nullable();
                $table->string('tagline', 255)->nullable();
                $table->text('mission')->nullable();
                $table->json('tone_of_voice')->nullable(); // tone adjectives, archetype, avoid_words, use_words
                $table->json('target_audience')->nullable(); // personas, demographics, pain_points
                $table->json('value_propositions')->nullable(); // differentiators, proof_points
                $table->json('compliance_guidelines')->nullable(); // prohibited_claims, mandatory_disclaimers
                $table->json('visual_direction')->nullable(); // primary_colors, font_families, imagery_notes
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['workspace_id', 'is_default']);
            });
        }

        // 2. Knowledge Documents Table
        if (! Schema::hasTable('mrfox_knowledge_documents')) {
            Schema::create('mrfox_knowledge_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 255);
                $table->string('filename', 255)->nullable();
                $table->string('file_type', 50)->default('text'); // pdf, docx, md, txt, html, csv, json
                $table->string('file_path', 500)->nullable();
                $table->string('checksum', 64)->nullable();
                $table->enum('visibility', ['public', 'workspace', 'restricted', 'confidential'])->default('workspace');
                $table->enum('sensitivity_level', ['low', 'medium', 'high', 'restricted'])->default('low');
                $table->unsignedInteger('version')->default(1);
                $table->unsignedInteger('chunk_count')->default(0);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'visibility', 'sensitivity_level']);
            });
        }

        // 3. Knowledge Document Chunks Table (for Lexical & Hybrid Search)
        if (! Schema::hasTable('mrfox_knowledge_chunks')) {
            Schema::create('mrfox_knowledge_chunks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('mrfox_knowledge_documents')->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->unsignedInteger('chunk_index')->default(0);
                $table->unsignedInteger('page_number')->nullable();
                $table->longText('content');
                $table->unsignedInteger('token_count')->default(0);
                $table->string('vector_id', 100)->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'document_id']);
            });
        }

        // 4. Social Interactions & Communications Ingestion Table
        if (! Schema::hasTable('mrfox_social_interactions')) {
            Schema::create('mrfox_social_interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('platform', 50); // meta_facebook, meta_instagram, linkedin, tiktok, twitter_x, email
                $table->string('external_id', 150);
                $table->string('sender_name', 150)->nullable();
                $table->string('sender_handle', 150)->nullable();
                $table->text('message_content');
                $table->enum('sentiment', ['positive', 'neutral', 'negative', 'urgent'])->default('neutral');
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('status', ['new', 'in_progress', 'drafted', 'replied', 'closed'])->default('new');
                $table->text('drafted_reply')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status', 'priority']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mrfox_social_interactions');
        Schema::dropIfExists('mrfox_knowledge_chunks');
        Schema::dropIfExists('mrfox_knowledge_documents');
        Schema::dropIfExists('mrfox_brand_profiles');
    }
};
