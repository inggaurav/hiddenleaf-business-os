<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Complete Plugin Migration
 *
 * Depends on core tables: organizations, workspaces, users, hr_employees (core),
 * hr_payslips (core), hr_salary_components (core).
 *
 * Core HRM tables (hr_employees, hr_attendance, hr_leave_requests, hr_leave_types,
 * hr_holidays, hr_salary_components, hr_payslips, hr_payslip_lines, hr_appraisals,
 * hr_documents) are created by the BusinessOS core migration. This plugin extends them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Extend hr_employees with full profile ─────────────────────────
        Schema::table('hr_employees', function (Blueprint $t) {
            if (! Schema::hasColumn('hr_employees', 'phone')) {
                $t->string('phone')->nullable()->after('email');
                $t->string('gender')->nullable()->after('phone');
                $t->date('date_of_birth')->nullable()->after('gender');
                $t->string('blood_group')->nullable()->after('date_of_birth');
                $t->string('marital_status')->nullable()->after('blood_group');
                $t->string('nationality')->nullable()->after('marital_status');
                $t->string('national_id')->nullable()->after('nationality');
                $t->string('passport_number')->nullable()->after('national_id');
                $t->date('passport_expiry')->nullable()->after('passport_number');
                $t->text('address')->nullable()->after('passport_expiry');
                $t->string('city')->nullable()->after('address');
                $t->string('state')->nullable()->after('city');
                $t->string('country')->nullable()->after('state');
                $t->string('pincode')->nullable()->after('country');
                $t->string('emergency_contact_name')->nullable()->after('pincode');
                $t->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
                $t->string('emergency_contact_relation')->nullable()->after('emergency_contact_phone');
                $t->string('bank_name')->nullable()->after('emergency_contact_relation');
                $t->string('bank_account_number')->nullable()->after('bank_name');
                $t->string('bank_ifsc')->nullable()->after('bank_account_number');
                $t->string('pan_number')->nullable()->after('bank_ifsc');
                $t->string('uan_number')->nullable()->after('pan_number');
                $t->string('pf_number')->nullable()->after('uan_number');
                $t->string('esi_number')->nullable()->after('pf_number');
                $t->string('employment_type')->default('full_time')->after('esi_number');
                $t->string('probation_status')->nullable()->after('employment_type');
                $t->date('probation_end_date')->nullable()->after('probation_status');
                $t->date('confirmation_date')->nullable()->after('probation_end_date');
                $t->string('avatar_path')->nullable()->after('confirmation_date');
                $t->text('bio')->nullable()->after('avatar_path');
                $t->string('linkedin_url')->nullable()->after('bio');
                $t->integer('notice_period_days')->nullable()->after('linkedin_url');
                $t->string('exit_reason')->nullable()->after('notice_period_days');
                $t->date('exit_date')->nullable()->after('exit_reason');
            }
        });

        // ── Recruitment: Job Positions ─────────────────────────────────────
        Schema::create('hr_job_positions', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->text('requirements')->nullable();
            $t->string('employment_type')->default('full_time');
            $t->string('location')->nullable();
            $t->decimal('salary_min', 18, 2)->nullable();
            $t->decimal('salary_max', 18, 2)->nullable();
            $t->integer('openings')->default(1);
            $t->string('status')->default('open');
            $t->date('posted_on')->nullable();
            $t->date('closes_on')->nullable();
            $t->foreignId('hiring_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['workspace_id', 'status']);
        });

        // ── Recruitment: Candidates ────────────────────────────────────────
        Schema::create('hr_candidates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_position_id')->nullable()->constrained('hr_job_positions')->nullOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('resume_path')->nullable();
            $t->string('source')->nullable();
            $t->string('current_company')->nullable();
            $t->string('current_designation')->nullable();
            $t->decimal('current_salary', 18, 2)->nullable();
            $t->decimal('expected_salary', 18, 2)->nullable();
            $t->integer('notice_period_days')->nullable();
            $t->date('available_from')->nullable();
            $t->string('stage')->default('applied');
            $t->string('status')->default('active');
            $t->text('notes')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['workspace_id', 'stage', 'status']);
        });

        // ── Recruitment: Interviews ────────────────────────────────────────
        Schema::create('hr_candidate_interviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('candidate_id')->constrained('hr_candidates')->cascadeOnDelete();
            $t->string('round_name');
            $t->string('interview_type')->default('in_person');
            $t->dateTime('scheduled_at');
            $t->integer('duration_minutes')->default(60);
            $t->string('location_or_link')->nullable();
            $t->string('status')->default('scheduled');
            $t->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->text('feedback')->nullable();
            $t->string('decision')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['workspace_id', 'candidate_id', 'status']);
        });

        // ── Onboarding: Templates ──────────────────────────────────────────
        Schema::create('hr_onboarding_templates', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('title');
            $t->text('description')->nullable();
            $t->boolean('is_default')->default(false);
        });

        Schema::create('hr_onboarding_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->constrained('hr_onboarding_templates')->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category')->default('general');
            $t->integer('due_offset_days')->default(0);
            $t->string('assigned_role')->nullable();
            $t->boolean('requires_document')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });

        // ── Onboarding: Per-employee ───────────────────────────────────────
        Schema::create('hr_employee_onboardings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->foreignId('template_id')->nullable()->constrained('hr_onboarding_templates')->nullOnDelete();
            $t->string('status')->default('in_progress');
            $t->date('started_on');
            $t->date('target_completion_on')->nullable();
            $t->date('completed_on')->nullable();
            $t->foreignId('assigned_buddy')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id']);
        });

        Schema::create('hr_employee_onboarding_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('onboarding_id')->constrained('hr_employee_onboardings')->cascadeOnDelete();
            $t->foreignId('onboarding_task_id')->nullable()->constrained('hr_onboarding_tasks')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category')->default('general');
            $t->date('due_on')->nullable();
            $t->string('status')->default('pending');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // ── Training ───────────────────────────────────────────────────────
        Schema::create('hr_training_programs', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category')->default('technical');
            $t->string('mode')->default('in_person');
            $t->string('trainer_name')->nullable();
            $t->string('trainer_contact')->nullable();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->integer('duration_hours')->nullable();
            $t->decimal('cost_per_head', 18, 2)->default(0);
            $t->string('status')->default('planned');
            $t->integer('max_participants')->nullable();
            $t->string('location')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('hr_training_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('program_id')->constrained('hr_training_programs')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('status')->default('enrolled');
            $t->decimal('score', 5, 2)->nullable();
            $t->boolean('passed')->default(false);
            $t->string('certificate_path')->nullable();
            $t->text('feedback')->nullable();
            $t->timestamps();
            $t->unique(['program_id', 'employee_id']);
        });

        // ── Timesheets ─────────────────────────────────────────────────────
        Schema::create('hr_timesheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->date('work_date');
            $t->string('project_code')->nullable();
            $t->text('description')->nullable();
            $t->decimal('hours', 5, 2);
            $t->string('status')->default('draft');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'work_date', 'status']);
        });

        // ── Disciplinary ───────────────────────────────────────────────────
        Schema::create('hr_disciplinary_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('case_number')->nullable();
            $t->string('type');
            $t->string('severity')->default('minor');
            $t->date('incident_date');
            $t->text('incident_description');
            $t->string('action_taken')->nullable();
            $t->text('action_notes')->nullable();
            $t->date('action_date')->nullable();
            $t->string('status')->default('open');
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['workspace_id', 'status']);
        });

        // ── Exit Management ────────────────────────────────────────────────
        Schema::create('hr_exit_interviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->date('interview_date');
            $t->string('exit_reason');
            $t->string('would_return')->default('maybe');
            $t->text('likes_about_company')->nullable();
            $t->text('dislikes_about_company')->nullable();
            $t->text('suggestions')->nullable();
            $t->unsignedTinyInteger('overall_rating')->nullable();
            $t->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id']);
        });

        Schema::create('hr_exit_clearances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('department');
            $t->string('clearance_item');
            $t->string('status')->default('pending');
            $t->text('remarks')->nullable();
            $t->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cleared_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });

        // ── Payroll Runs ───────────────────────────────────────────────────
        Schema::create('hr_payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('run_number');
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status')->default('draft');
            $t->integer('employee_count')->default(0);
            $t->decimal('total_gross', 18, 2)->default(0);
            $t->decimal('total_deductions', 18, 2)->default(0);
            $t->decimal('total_net', 18, 2)->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'period_start', 'period_end']);
            $t->index(['workspace_id', 'status']);
        });

        // Link payslips to payroll run
        if (Schema::hasTable('hr_payslips') && ! Schema::hasColumn('hr_payslips', 'payroll_run_id')) {
            Schema::table('hr_payslips', function (Blueprint $t) {
                $t->foreignId('payroll_run_id')->nullable()->after('created_by')
                    ->constrained('hr_payroll_runs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hr_payslips', 'payroll_run_id')) {
            Schema::table('hr_payslips', fn (Blueprint $t) => $t->dropForeign(['payroll_run_id']));
            Schema::table('hr_payslips', fn (Blueprint $t) => $t->dropColumn('payroll_run_id'));
        }

        foreach ([
            'hr_exit_clearances', 'hr_exit_interviews',
            'hr_disciplinary_cases',
            'hr_timesheets',
            'hr_training_enrollments', 'hr_training_programs',
            'hr_employee_onboarding_tasks', 'hr_employee_onboardings',
            'hr_onboarding_tasks', 'hr_onboarding_templates',
            'hr_candidate_interviews', 'hr_candidates',
            'hr_job_positions',
            'hr_payroll_runs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        // Drop extended employee columns
        Schema::table('hr_employees', function (Blueprint $t) {
            foreach ([
                'phone','gender','date_of_birth','blood_group','marital_status',
                'nationality','national_id','passport_number','passport_expiry',
                'address','city','state','country','pincode',
                'emergency_contact_name','emergency_contact_phone','emergency_contact_relation',
                'bank_name','bank_account_number','bank_ifsc',
                'pan_number','uan_number','pf_number','esi_number',
                'employment_type','probation_status','probation_end_date',
                'confirmation_date','avatar_path','bio','linkedin_url',
                'notice_period_days','exit_reason','exit_date',
            ] as $col) {
                if (Schema::hasColumn('hr_employees', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }

    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
        $t->timestamps();
    }
};
