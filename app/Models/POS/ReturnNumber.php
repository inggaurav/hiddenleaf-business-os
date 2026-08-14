<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class ReturnNumber extends Model
{
    protected $table = 'pos_return_numbers';

    protected $fillable = ['workspace_id', 'date', 'last_number'];
}
