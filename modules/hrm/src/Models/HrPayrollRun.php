<?php

namespace HiddenLeaf\Hrm\Models;

use App\Models\HrPayslip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayrollRun extends Model
{
    protected $table = 'hr_payroll_runs';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'run_number',
        'period_start',
        'period_end',
        'status',
        'employee_count',
        'total_gross',
        'total_deductions',
        'total_net',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'employee_count' => 'integer',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(HrPayslip::class, 'payroll_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
