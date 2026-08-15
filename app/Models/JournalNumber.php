<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalNumber extends Model
{
    protected $fillable = [
        'workspace_id',
        'date',
        'last_number',
    ];
}
