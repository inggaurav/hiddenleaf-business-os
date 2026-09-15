<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Modules\SecureModuleInstaller;

$installer = new SecureModuleInstaller();

$zipPath = __DIR__ . '/../hrm.zip';
echo "Testing SecureModuleInstaller on " . realpath($zipPath) . "\n";
echo "Public key config: " . config('modules.public_key') . "\n";
echo "Core version: " . config('modules.core_version') . "\n";
echo "Modules dir: " . config('modules.directory') . "\n";

try {
    // We can test the verify logic using reflection or by creating a temporary copy
    $ref = new ReflectionClass(SecureModuleInstaller::class);
    $verifyMethod = $ref->getMethod('verify');
    $verifyMethod->setAccessible(true);

    $zip = new ZipArchive();
    $zip->open($zipPath);
    $manifest = json_decode($zip->getFromName('module.json'), true);
    $zip->close();

    $verifyMethod->invoke($installer, $manifest);
    echo "SUCCESS: Manifest verify passed!\n";
} catch (\Throwable $e) {
    echo "FAILURE: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
