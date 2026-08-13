<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPipeline extends Model
{
    protected $table = 'crm_pipelines';

    protected $fillable = ['organization_id', 'workspace_id', 'name', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function stages()
    {
        return $this->hasMany(CrmStage::class, 'pipeline_id')->orderBy('position');
    }
}
