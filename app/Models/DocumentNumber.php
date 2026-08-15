<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumber extends Model
{
    protected $fillable = [
        'workspace_id',
        'type',
        'date',
        'last_number',
    ];
}
