<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChFavorite extends Model
{
    use HasFactory;

    protected $table = 'ch_favorites';

    protected $fillable = [
        'user_id',
        'favorite_id',
    ];
}
