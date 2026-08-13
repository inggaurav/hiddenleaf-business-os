<?php

namespace Tests\Support;

class LicenseTestKeys
{
    public static function get(): array
    {
        return [
            'private' => file_get_contents(__DIR__.'/../Fixtures/license-private.pem'),
            'public' => file_get_contents(__DIR__.'/../Fixtures/license-public.pem'),
        ];
    }
}
