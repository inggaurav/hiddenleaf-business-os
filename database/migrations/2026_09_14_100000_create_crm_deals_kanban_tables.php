<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend crm_deals with probability, close dates, lost_reason, source, assigned_to, position
        Schema::table('crm_deals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deals', 'probability')) {
                $table->integer('probability')->default(0)->after('value');
            }
            if (! Schema::hasColumn('crm_deals', 'expected_close_date')) {
                $table->date('expected_close_date')->nullable()->after('expected_close_on');
            }
            if (! Schema::hasColumn('crm_deals', 'actual_close_date')) {
                $table->date('actual_close_date')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('crm_deals', 'lost_reason')) {
                $table->string('lost_reason')->nullable()->after('loss_reason');
            }
            if (! Schema::hasColumn('crm_deals', 'source')) {
                $table->string('source')->nullable()->after('name');
            }
            if (! Schema::hasColumn('crm_deals', 'position')) {
                $table->integer('position')->default(0)->after('stage_id');
            }
            if (! Schema::hasColumn('crm_deals', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // 2. crm_deal_activities table
        if (! Schema::hasTable('crm_deal_activities')) {
            Schema::create('crm_deal_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type'); // call, email, meeting, note, task
                $table->string('subject');
                $table->text('body')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['deal_id', 'created_at']);
            });
        }

        // 3. crm_deal_files table
        if (! Schema::hasTable('crm_deal_files')) {
            Schema::create('crm_deal_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('file_path')->nullable();
                $table->string('file_name')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('path')->nullable();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('mime_type')->nullable();
                $table->timestamps();

                $table->index('deal_id');
                $table->index('lead_id');
            });
        }

        // 4. crm_deal_approvals table
        if (! Schema::hasTable('crm_deal_approvals')) {
            Schema::create('crm_deal_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('notes')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['deal_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deal_approvals');
        Schema::dropIfExists('crm_deal_files');
        Schema::dropIfExists('crm_deal_activities');

        Schema::table('crm_deals', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['probability', 'expected_close_date', 'actual_close_date', 'lost_reason', 'source', 'position'] as $col) {
                if (Schema::hasColumn('crm_deals', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (! empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }
};
