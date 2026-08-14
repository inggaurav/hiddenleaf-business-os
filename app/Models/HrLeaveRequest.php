<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrLeaveRequest extends Model
{
    protected $table = 'hr_leave_requests';

    protected $fillable = ['organization_id', 'workspace_id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'days' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function type()
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }
}
