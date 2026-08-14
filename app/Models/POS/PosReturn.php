<?php

namespace App\Models\POS;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class PosReturn extends Model
{
    protected $table = 'pos_returns';

    protected $fillable = [
        'organization_id', 'workspace_id', 'pos_sale_id', 'return_number',
        'status', 'reason', 'refund_amount', 'refund_method', 'refund_reference',
        'journal_entry_id', 'processed_by', 'processed_at', 'created_by',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'refund_amount' => 'string',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $return) {
            if ($return->getOriginal('status') === 'completed') {
                throw new RuntimeException('Completed POS returns are immutable.');
            }
        });
        static::deleting(function (self $return) {
            if ($return->status === 'completed') {
                throw new RuntimeException('Completed POS returns cannot be deleted.');
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosReturnItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
