<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(ProductServiceItem::class, 'category_id');
    }
}
