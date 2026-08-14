<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'warehouse_id',
        'product_id',
        'type',
        'quantity',
        'direction',
        'balance_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'reason',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Stock movements are immutable. Record a compensating movement instead.');
        });
        static::deleting(function () {
            throw new RuntimeException('Stock movements are immutable and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'direction' => 'integer',
            'balance_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
