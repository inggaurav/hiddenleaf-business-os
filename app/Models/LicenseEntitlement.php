<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseEntitlement extends Model
{
    protected $fillable = ['license_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
