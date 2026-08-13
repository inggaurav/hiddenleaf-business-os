<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductServiceUnit extends Model
{
    protected $fillable = ['name', 'symbol', 'organization_id', 'workspace_id'];
}
