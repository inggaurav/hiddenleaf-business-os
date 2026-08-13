<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    protected $fillable = ['license_id', 'key_hash', 'key_prefix', 'status', 'revoked_at'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public static function digest(string $plainTextKey): string
    {
        return hash('sha256', strtoupper(trim($plainTextKey)));
    }
}
