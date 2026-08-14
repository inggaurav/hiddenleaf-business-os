<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrmDeal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = ['organization_id', 'workspace_id', 'lead_id', 'pipeline_id', 'stage_id', 'assigned_to', 'name', 'value', 'expected_close_on', 'status', 'closed_at', 'loss_reason'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'expected_close_on' => 'date', 'closed_at' => 'datetime'];
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }

    public function pipeline()
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage()
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }
}
