<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\HrEmployee as BaseEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HrEmployee extends BaseEmployee
{
    protected $table = 'hr_employees';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'branch_id',
        'department_id',
        'designation_id',
        'shift_id',
        'employee_number',
        'name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'blood_group',
        'marital_status',
        'nationality',
        'national_id',
        'passport_number',
        'passport_expiry',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'pan_number',
        'uan_number',
        'pf_number',
        'esi_number',
        'employment_type',
        'probation_status',
        'probation_end_date',
        'confirmation_date',
        'avatar_path',
        'bio',
        'linkedin_url',
        'notice_period_days',
        'exit_reason',
        'exit_date',
        'joined_at',
        'ended_at',
        'basic_salary',
        'status',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'date_of_birth' => 'date',
            'passport_expiry' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'exit_date' => 'date',
            'notice_period_days' => 'integer',
        ]);
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(HrEmployeeOnboarding::class, 'employee_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(HrTimesheet::class, 'employee_id');
    }

    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(HrTrainingEnrollment::class, 'employee_id');
    }

    public function disciplinaryCases(): HasMany
    {
        return $this->hasMany(HrDisciplinaryCase::class, 'employee_id');
    }

    public function exitInterview(): HasOne
    {
        return $this->hasOne(HrExitInterview::class, 'employee_id');
    }

    public function exitClearances(): HasMany
    {
        return $this->hasMany(HrExitClearance::class, 'employee_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(\App\Models\HrPayslip::class, 'employee_id');
    }
}
