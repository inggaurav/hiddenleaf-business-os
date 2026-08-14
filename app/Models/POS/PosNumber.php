<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class PosNumber extends Model
{
    protected $table = 'pos_numbers';

    protected $fillable = ['workspace_id', 'date', 'last_number'];
}
