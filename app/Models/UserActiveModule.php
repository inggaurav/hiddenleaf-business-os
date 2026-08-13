<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActiveModule extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'module_name'];
}
