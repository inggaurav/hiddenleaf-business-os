<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_branches', fn (Blueprint $t) => $this->tenantNamed($t));
        Schema::create('hr_departments', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
        });
        Schema::create('hr_designations', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
        });
        Schema::create('hr_shifts', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->time('starts_at');
            $t->time('ends_at');
            $t->unsignedInteger('grace_minutes')->default(0);
        });
        Schema::create('hr_employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $t->foreignId('designation_id')->nullable()->constrained('hr_designations')->nullOnDelete();
            $t->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $t->string('employee_number');
            $t->string('name');
            $t->string('email')->nullable();
            $t->date('joined_at');
            $t->date('ended_at')->nullable();
            $t->decimal('basic_salary', 18, 2)->default(0);
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['workspace_id', 'employee_number']);
            $t->unique(['workspace_id', 'email']);
        });
        Schema::create('hr_attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->date('attendance_date');
            $t->dateTime('clock_in')->nullable();
            $t->dateTime('clock_out')->nullable();
            $t->string('status');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'attendance_date']);
        });
        Schema::create('hr_leave_types', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->decimal('annual_allowance', 8, 2)->default(0);
            $t->boolean('is_paid')->default(true);
        });
        Schema::create('hr_leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained('hr_leave_types')->restrictOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('days', 8, 2);
            $t->text('reason')->nullable();
            $t->string('status')->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamps();
        });
        Schema::create('hr_holidays', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->date('holiday_date');
            $t->boolean('is_optional')->default(false);
            $t->unique(['workspace_id', 'holiday_date']);
        });
        Schema::create('hr_salary_components', function (Blueprint $t) {
            $this->tenantNamed($t);
            $t->string('type');
            $t->string('calculation')->default('fixed');
            $t->decimal('value', 18, 4);
            $t->boolean('is_taxable')->default(true);
        });
        Schema::create('hr_employee_salary_components', function (Blueprint $t) {
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->foreignId('salary_component_id')->constrained('hr_salary_components')->cascadeOnDelete();
            $t->decimal('value', 18, 4)->nullable();
            $t->primary(['employee_id', 'salary_component_id']);
        });
        Schema::create('hr_payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('gross_pay', 18, 2);
            $t->decimal('deductions', 18, 2);
            $t->decimal('net_pay', 18, 2);
            $t->string('status')->default('draft');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'period_start', 'period_end']);
        });
        Schema::create('hr_payslip_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payslip_id')->constrained('hr_payslips')->cascadeOnDelete();
            $t->string('name');
            $t->string('type');
            $t->decimal('amount', 18, 2);
            $t->timestamps();
        });
        Schema::create('hr_appraisals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->date('review_date');
            $t->unsignedTinyInteger('rating');
            $t->text('feedback')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('hr_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('name');
            $t->string('storage_disk');
            $t->string('storage_path');
            $t->string('mime_type')->nullable();
            $t->date('expires_on')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['hr_documents', 'hr_appraisals', 'hr_payslip_lines', 'hr_payslips', 'hr_employee_salary_components', 'hr_salary_components', 'hr_holidays', 'hr_leave_requests', 'hr_leave_types', 'hr_attendance', 'hr_employees', 'hr_shifts', 'hr_designations', 'hr_departments', 'hr_branches'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function tenantNamed(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
        $table->unique(['workspace_id', 'name']);
    }
};
