<?php

namespace App\Models;

use HiddenLeaf\CrmDealsKanban\Models\CrmDealActivity;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealApproval;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CrmDeal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'lead_id',
        'pipeline_id',
        'stage_id',
        'position',
        'assigned_to',
        'name',
        'value',
        'probability',
        'expected_close_on',
        'expected_close_date',
        'status',
        'closed_at',
        'actual_close_date',
        'loss_reason',
        'lost_reason',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'integer',
            'position' => 'integer',
            'expected_close_on' => 'date',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'actual_close_date' => 'date',
        ];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmDealActivity::class, 'deal_id')->latest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(CrmDealFile::class, 'deal_id')->latest();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CrmDealApproval::class, 'deal_id')->latest();
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'subject');
    }

    // Accessors and mutators to sync complementary column names
    public function getExpectedCloseDateAttribute($value)
    {
        return $value ?: $this->attributes['expected_close_on'] ?? null;
    }

    public function setExpectedCloseDateAttribute($value): void
    {
        $this->attributes['expected_close_date'] = $value;
        $this->attributes['expected_close_on'] = $value;
    }

    public function getActualCloseDateAttribute($value)
    {
        if ($value) return $value;
        if (! empty($this->attributes['closed_at'])) {
            return substr($this->attributes['closed_at'], 0, 10);
        }
        return null;
    }

    public function setActualCloseDateAttribute($value): void
    {
        $this->attributes['actual_close_date'] = $value;
        if (! empty($value) && empty($this->attributes['closed_at'])) {
            $this->attributes['closed_at'] = $value . ' 00:00:00';
        }
    }

    public function getLostReasonAttribute($value)
    {
        return $value ?: $this->attributes['loss_reason'] ?? null;
    }

    public function setLostReasonAttribute($value): void
    {
        $this->attributes['lost_reason'] = $value;
        $this->attributes['loss_reason'] = $value;
    }
}
