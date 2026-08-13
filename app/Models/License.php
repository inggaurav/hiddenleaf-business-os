<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = ['customer_id', 'product_id', 'edition_id', 'license_type_id', 'status', 'activation_limit', 'starts_at', 'expires_at', 'grace_period_days'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(LicenseProduct::class, 'product_id');
    }

    public function customer()
    {
        return $this->belongsTo(LicenseCustomer::class, 'customer_id');
    }

    public function edition()
    {
        return $this->belongsTo(LicenseEdition::class, 'edition_id');
    }

    public function type()
    {
        return $this->belongsTo(LicenseType::class, 'license_type_id');
    }

    public function keys()
    {
        return $this->hasMany(LicenseKey::class);
    }

    public function domains()
    {
        return $this->hasMany(LicenseDomain::class);
    }

    public function entitlements()
    {
        return $this->hasMany(LicenseEntitlement::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }
}
