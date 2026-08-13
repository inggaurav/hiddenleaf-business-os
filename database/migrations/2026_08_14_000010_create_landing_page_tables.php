<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->jsonb('seo_keywords')->nullable();
            $t->boolean('is_published')->default(false);
            $t->string('locale', 10)->default('en');
            $t->timestamps();
        });
        Schema::create('landing_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained('landing_sites')->cascadeOnDelete();
            $t->string('type');
            $t->string('heading')->nullable();
            $t->text('subheading')->nullable();
            $t->jsonb('content')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
            $t->index(['site_id', 'position']);
        });
        Schema::create('landing_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained('landing_sites')->cascadeOnDelete();
            $t->string('slug');
            $t->string('title');
            $t->longText('content');
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->boolean('is_published')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('landing_sections');
        Schema::dropIfExists('landing_sites');
    }
};
