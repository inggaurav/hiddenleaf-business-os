<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('suggestion_categories')) {
            Schema::create('suggestion_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('color', 7)->default('#FF6B6B');
                $table->longText('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();

                $table->index(['workspace_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('suggestions')) {
            Schema::create('suggestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->longText('description');
                $table->foreignId('category_id')->nullable()->constrained('suggestion_categories')->nullOnDelete();
                $table->enum('status', ['new', 'under_review', 'accepted', 'rejected', 'complete'])->default('new');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_anonymous')->default(false);
                $table->integer('votes_count')->default(0);
                $table->integer('views_count')->default(0);
                $table->longText('admin_response')->nullable();
                $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['workspace_id', 'votes_count']);
            });
        }

        if (!Schema::hasTable('suggestion_votes')) {
            Schema::create('suggestion_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('suggestion_id')->constrained('suggestions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['suggestion_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('suggestion_status_histories')) {
            Schema::create('suggestion_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('suggestion_id')->constrained('suggestions')->cascadeOnDelete();
                $table->string('old_status');
                $table->string('new_status');
                $table->longText('comment')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('suggestion_views')) {
            Schema::create('suggestion_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('suggestion_id')->constrained('suggestions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['suggestion_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_views');
        Schema::dropIfExists('suggestion_status_histories');
        Schema::dropIfExists('suggestion_votes');
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('suggestion_categories');
    }
};
