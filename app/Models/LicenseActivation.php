<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    protected $fillable = ['license_id', 'license_key_id', 'installation_id', 'domain', 'status', 'activated_at', 'deactivated_at'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
