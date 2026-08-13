<?php

namespace App\Traits;

use App\Observers\AuditObserver;

trait Auditable
{
    public static function bootAuditable()
    {
        static::observe(AuditObserver::class);
    }
}
