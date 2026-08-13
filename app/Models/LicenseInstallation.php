<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseInstallation extends Model
{
    protected $fillable = ['license_id', 'installation_uuid', 'metadata', 'last_seen_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_seen_at' => 'datetime'];
    }
}
