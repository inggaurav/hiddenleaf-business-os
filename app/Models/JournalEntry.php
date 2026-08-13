<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'entry_number', 'entry_date', 'reference', 'description', 'status', 'posted_at', 'created_by', 'posted_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
