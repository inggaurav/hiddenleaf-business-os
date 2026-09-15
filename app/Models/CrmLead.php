<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrmLead extends Model
{
    protected $table = 'crm_leads';

    protected $fillable = ['organization_id', 'workspace_id', 'pipeline_id', 'stage_id', 'source_id', 'assigned_to', 'name', 'email', 'phone', 'company', 'estimated_value', 'status', 'converted_at', 'created_by'];

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'converted_at' => 'datetime'];
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

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes()
    {
        return $this->morphMany(CrmNote::class, 'subject');
    }

    public function activities()
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    public function files()
    {
        return $this->hasMany(\HiddenLeaf\CrmDealsKanban\Models\CrmDealFile::class, 'lead_id');
    }
}
