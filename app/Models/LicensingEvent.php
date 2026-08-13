<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensingEvent extends Model
{
    protected $fillable = ['license_id', 'license_key_id', 'activation_id', 'event', 'payload', 'ip_address'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
