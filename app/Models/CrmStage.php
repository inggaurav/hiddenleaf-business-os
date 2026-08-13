<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmStage extends Model
{
    protected $table = 'crm_stages';

    protected $fillable = ['pipeline_id', 'name', 'position', 'probability', 'is_closed', 'outcome'];

    protected function casts(): array
    {
        return ['probability' => 'decimal:2', 'is_closed' => 'boolean'];
    }
}
