<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_deal_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deal_activities', 'workspace_id')) {
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_deal_activities', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('crm_deal_activities', 'due_date')) {
                $table->timestamp('due_date')->nullable();
            }
        });

        Schema::table('crm_deal_files', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deal_files', 'workspace_id')) {
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_deal_files', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('crm_deal_files', 'path')) {
                $table->string('path')->nullable();
            }
            if (! Schema::hasColumn('crm_deal_files', 'size')) {
                $table->unsignedBigInteger('size')->nullable();
            }
            if (! Schema::hasColumn('crm_deal_files', 'mime_type')) {
                $table->string('mime_type')->nullable();
            }
        });

        Schema::table('crm_deal_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deal_approvals', 'workspace_id')) {
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_deal_approvals', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_deal_approvals', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->default(0);
            }
            if (! Schema::hasColumn('crm_deal_approvals', 'approval_status')) {
                $table->string('approval_status')->default('pending');
            }
        });
    }

    public function down(): void
    {
        // No-op or drop columns
    }
};