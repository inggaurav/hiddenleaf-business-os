<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TasklyStage extends Model
{
    protected $table = 'taskly_stages';

    protected $fillable = ['project_id', 'name', 'position', 'is_complete'];

    protected function casts(): array
    {
        return ['is_complete' => 'boolean'];
    }
}
