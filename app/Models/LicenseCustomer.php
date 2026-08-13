<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCustomer extends Model
{
    protected $fillable = ['name', 'email', 'status'];
}
