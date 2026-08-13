<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPayslip extends Model
{
    protected $table = 'hr_payslips';

    protected $fillable = ['organization_id', 'workspace_id', 'employee_id', 'period_start', 'period_end', 'gross_pay', 'deductions', 'net_pay', 'status', 'created_by', 'paid_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'gross_pay' => 'decimal:2', 'deductions' => 'decimal:2', 'net_pay' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(HrPayslipLine::class, 'payslip_id');
    }
}
