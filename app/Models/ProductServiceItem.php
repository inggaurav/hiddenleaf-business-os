<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'description',
        'type',
        'sale_price',
        'purchase_price',
        'reorder_level',
        'unit',
        'category_id',
        'is_active',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, ?int $organizationId, ?int $workspaceId): Builder
    {
        return $query
            ->when($organizationId, fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->when($workspaceId, fn (Builder $builder) => $builder->where('workspace_id', $workspaceId));
    }

    public function category()
    {
        return $this->belongsTo(ProductServiceCategory::class, 'category_id');
    }

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class, 'product_id');
    }

    public function taxes()
    {
        return $this->belongsToMany(ProductServiceTax::class, 'product_service_item_taxes');
    }
}
