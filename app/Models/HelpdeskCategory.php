<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    public function tickets()
    {
        return $this->hasMany(HelpdeskTicket::class, 'category_id');
    }
}
