<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductServiceTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate',
        'is_compound',
        'organization_id',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_compound' => 'boolean',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ProductServiceItem::class, 'product_service_item_taxes');
    }
}
