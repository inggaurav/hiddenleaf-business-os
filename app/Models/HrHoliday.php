<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HrHoliday extends Model
{
    protected $table = 'hr_holidays';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'name',
        'holiday_date',
        'is_optional',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_optional' => 'boolean',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }
}
