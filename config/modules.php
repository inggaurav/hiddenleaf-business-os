<?php

return ['public_key' => env('MODULE_PUBLIC_KEY', env('LICENSING_PUBLIC_KEY')), 'directory' => base_path('modules'), 'maximum_bytes' => 50 * 1024 * 1024, 'core_version' => env('APP_VERSION', '1.0.0')];
