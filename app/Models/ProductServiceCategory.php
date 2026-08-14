<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'color',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductServiceItem::class, 'category_id');
    }
}
