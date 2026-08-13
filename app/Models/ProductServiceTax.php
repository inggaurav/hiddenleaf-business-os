<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductServiceTax extends Model
{
    protected $fillable = ['name', 'rate', 'is_compound', 'organization_id', 'workspace_id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'is_compound' => 'boolean'];
    }
}
