<?php

$baseDir = dirname(__DIR__);
$pluginDir = $baseDir . DIRECTORY_SEPARATOR . 'crm-deals-kanban-plugin';
$zipPath = $baseDir . DIRECTORY_SEPARATOR . 'crm-deals-kanban.zip';
$manifestPath = $pluginDir . DIRECTORY_SEPARATOR . 'module.json';

if (!is_dir($pluginDir)) {
    fwrite(STDERR, "crm-deals-kanban-plugin directory not found!\n");
    exit(1);
}

$hashes = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $real = $item->getRealPath();
        $relative = substr($real, strlen(realpath($pluginDir)) + 1);
        $relative = str_replace('\\', '/', $relative);
        if ($relative !== 'module.json') {
            $hashes[$relative] = hash_file('sha256', $real);
        }
    }
}

ksort($hashes);
$checksum = hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo "Computed package checksum: {$checksum}\n";

$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$manifest['checksum'] = $checksum;

$canonical = $manifest;
unset($canonical['signature']);
ksort($canonical);

$canonicalJson = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$privateKeyPemPath = $baseDir . DIRECTORY_SEPARATOR . 'tests/Fixtures/license-private.pem';
if (file_exists($privateKeyPemPath)) {
    $privateKeyPem = file_get_contents($privateKeyPemPath);
    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if ($privateKey) {
        $signatureBinary = '';
        $ok = openssl_sign($canonicalJson, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        if ($ok) {
            $manifest['signature'] = base64_encode($signatureBinary);
            echo "Manifest signed successfully.\n";
        }
    }
}

file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create zip archive: {$zipPath}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $real = $item->getRealPath();
    $relative = substr($real, strlen(realpath($pluginDir)) + 1);
    $relative = str_replace('\\', '/', $relative);

    if ($item->isDir()) {
        $zip->addEmptyDir($relative);
    } elseif ($item->isFile()) {
        $zip->addFile($real, $relative);
    }
}

$zip->close();
echo "Packaged plugin successfully into: {$zipPath} (" . round(filesize($zipPath) / 1024, 2) . " KB)\n";