<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_customers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
            $table->unique(['workspace_id', 'user_id']);
        });
        Schema::table('account_vendors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('hr_event_types', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('kind', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['workspace_id', 'kind', 'name']);
        });
        Schema::create('hr_employee_events', function (Blueprint $table) {
            $this->tenant($table);
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained('hr_event_types')->nullOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->date('effective_date')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response')->nullable();
            $table->index(['workspace_id', 'kind', 'status']);
        });
        Schema::create('hr_announcements', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('title');
            $table->longText('content');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('hr_policies', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('title');
            $table->string('version')->default('1.0');
            $table->longText('content');
            $table->date('effective_on')->nullable();
            $table->boolean('requires_acknowledgement')->default(true);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('hr_policy_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('hr_policies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_id', 'employee_id']);
        });

        Schema::create('taskly_project_payments', function (Blueprint $table) {
            $this->tenant($table);
            $table->foreignId('project_id')->constrained('taskly_projects')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('account_customers')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method')->default('bank');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::create('taskly_project_statuses', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->boolean('is_closed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unique(['workspace_id', 'name']);
        });
        Schema::create('taskly_stage_templates', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('name');
            $table->boolean('is_complete')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('landing_marketplace_items', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
        });
        Schema::create('landing_newsletter_subscribers', function (Blueprint $table) {
            $this->tenant($table);
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('subscribed');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->unique(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        foreach ([
            'landing_newsletter_subscribers', 'landing_marketplace_items',
            'taskly_stage_templates', 'taskly_project_statuses', 'taskly_project_payments',
            'hr_policy_acknowledgements', 'hr_policies', 'hr_announcements', 'hr_employee_events', 'hr_event_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('account_vendors', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('account_customers', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }

    private function tenant(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    }
};
