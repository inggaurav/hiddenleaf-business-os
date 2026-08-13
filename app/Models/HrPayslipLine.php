<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPayslipLine extends Model
{
    protected $table = 'hr_payslip_lines';

    protected $fillable = ['payslip_id', 'name', 'type', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
