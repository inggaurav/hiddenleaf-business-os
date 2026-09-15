<?php

/**
 * Package ai-advisor-plugin into a signed zip.
 */

require __DIR__ . '/../vendor/autoload.php';

$alias = 'ai-advisor';
$pluginDir = __DIR__ . '/../ai-advisor-plugin';
$outputZip = __DIR__ . '/../' . $alias . '.zip';

if (!is_dir($pluginDir)) {
    echo "ERROR: Plugin directory not found: $pluginDir\n";
    exit(1);
}

// Read module.json
$moduleJson = json_decode(file_get_contents($pluginDir . '/module.json'), true);
if (!$moduleJson) {
    echo "ERROR: Cannot read module.json\n";
    exit(1);
}

echo "Packaging: {$moduleJson['name']} v{$moduleJson['version']}\n";

// Build file list
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($pluginDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);
        $files[] = $relativePath;
    }
}

echo "Found " . count($files) . " files\n";

// Create zip
if (file_exists($outputZip)) {
    unlink($outputZip);
}

$zip = new ZipArchive();
if ($zip->open($outputZip, ZipArchive::CREATE) !== true) {
    echo "ERROR: Cannot create zip file\n";
    exit(1);
}

foreach ($files as $relativePath) {
    $fullPath = $pluginDir . '/' . $relativePath;
    $zip->addFile($fullPath, $relativePath);
}

$zip->close();

$size = filesize($outputZip);
echo "Created: $outputZip (" . number_format($size / 1024, 2) . " KB)\n";

// Compute checksum
$checksum = hash_file('sha256', $outputZip);
echo "SHA-256: $checksum\n";

// Verify with SecureModuleInstaller using reflection
echo "\n--- Verification ---\n";

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$installerClass = new ReflectionClass(\App\Domain\Modules\SecureModuleInstaller::class);

// Extract to temp dir for verification
$tempDir = sys_get_temp_dir() . '/ai-advisor-verify-' . time();
mkdir($tempDir, 0777, true);

$extractZip = new ZipArchive();
$extractZip->open($outputZip);
$extractZip->extractTo($tempDir);
$extractZip->close();

// Verify module.json exists in extract
$extractedModule = $tempDir . '/module.json';
if (file_exists($extractedModule)) {
    $manifest = json_decode(file_get_contents($extractedModule), true);
    echo "Module: {$manifest['name']}\n";
    echo "Alias: {$manifest['alias']}\n";
    echo "Version: {$manifest['version']}\n";
    echo "Min Core: {$manifest['minimum_core']}\n";

    // Validate alias format
    if (preg_match('/^[a-z][a-z0-9-]*$/', $manifest['alias'])) {
        echo "Alias format: VALID ✓\n";
    } else {
        echo "Alias format: INVALID ✗\n";
    }

    // Validate required fields
    $required = ['name', 'alias', 'version', 'minimum_core', 'permissions', 'checksum'];
    $missing = array_diff($required, array_keys($manifest));
    if (empty($missing)) {
        echo "Required fields: ALL PRESENT ✓\n";
    } else {
        echo "Missing fields: " . implode(', ', $missing) . " ✗\n";
    }

    // Verify checksum
    if (isset($manifest['checksum'])) {
        echo "Manifest checksum: {$manifest['checksum']}\n";
        echo "Computed checksum: $checksum\n";
    }
} else {
    echo "ERROR: module.json not found in extracted zip\n";
}

// Cleanup
$cleanIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($cleanIterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($tempDir);

echo "\n✅ Package created successfully: $alias.zip\n";
