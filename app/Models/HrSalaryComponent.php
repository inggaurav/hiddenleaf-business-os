<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrSalaryComponent extends Model
{
    protected $table = 'hr_salary_components';

    protected $fillable = ['organization_id', 'workspace_id', 'name', 'type', 'calculation', 'value', 'is_taxable'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4', 'is_taxable' => 'boolean'];
    }
}
