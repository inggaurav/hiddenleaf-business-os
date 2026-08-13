<?php

return [
    'manifest_url' => env('UPDATE_MANIFEST_URL'),
    'public_key' => env('UPDATE_PUBLIC_KEY', env('LICENSING_PUBLIC_KEY')),
    'channel' => env('UPDATE_CHANNEL', 'stable'),
    'allowed_channels' => ['stable', 'beta'],
    'download_timeout' => (int) env('UPDATE_DOWNLOAD_TIMEOUT', 120),
    'backup_database' => (bool) env('UPDATE_BACKUP_DATABASE', true),
    'backup_directory' => storage_path('app/updates/backups'),
    'staging_directory' => storage_path('app/updates/staging'),
    'application_root' => base_path(),
];
