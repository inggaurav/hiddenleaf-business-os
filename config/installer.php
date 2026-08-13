<?php

return [
    'minimum_php' => '8.4.0',
    'persist_environment' => env('INSTALLER_PERSIST_ENV', true),
    'lock_file' => storage_path('installed'),
];
