<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrAttendance extends Model
{
    protected $table = 'hr_attendance';

    protected $fillable = ['organization_id', 'workspace_id', 'employee_id', 'attendance_date', 'clock_in', 'clock_out', 'status', 'notes'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime'];
    }
}
