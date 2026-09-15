<?php

namespace HiddenLeaf\Hrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOnboardingTask extends Model
{
    protected $table = 'hr_onboarding_tasks';

    protected $fillable = [
        'template_id',
        'title',
        'description',
        'category',
        'due_offset_days',
        'assigned_role',
        'requires_document',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_offset_days' => 'integer',
            'requires_document' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrOnboardingTemplate::class, 'template_id');
    }
}
