<?php

// Script to package and sign hrm-plugin into hrm.zip
$baseDir = dirname(__DIR__);
$pluginDir = $baseDir . DIRECTORY_SEPARATOR . 'hrm-plugin';
$zipPath = $baseDir . DIRECTORY_SEPARATOR . 'hrm.zip';
$manifestPath = $pluginDir . DIRECTORY_SEPARATOR . 'module.json';

if (!is_dir($pluginDir)) {
    fwrite(STDERR, "hrm-plugin directory not found!\n");
    exit(1);
}

// 1. Gather all files except module.json and calculate sha256
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

// 2. Read module.json and update checksum
$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$manifest['checksum'] = $checksum;

// 3. Prepare canonical manifest for signing
$canonical = $manifest;
unset($canonical['signature']);
ksort($canonical);

$canonicalJson = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

// 4. Sign with private key
$privateKeyPem = file_get_contents($baseDir . DIRECTORY_SEPARATOR . 'tests/Fixtures/license-private.pem');
$privateKey = openssl_pkey_get_private($privateKeyPem);
if (!$privateKey) {
    fwrite(STDERR, "Failed to load private key\n");
    exit(1);
}

$signatureBinary = '';
$ok = openssl_sign($canonicalJson, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
if (!$ok) {
    fwrite(STDERR, "Failed to sign manifest\n");
    exit(1);
}

$signatureBase64 = base64_encode($signatureBinary);
$manifest['signature'] = $signatureBase64;

// 5. Verify signature with public key right now to be 100% sure
$publicKeyPem = file_get_contents($baseDir . DIRECTORY_SEPARATOR . 'tests/Fixtures/license-public.pem');
$publicKey = openssl_pkey_get_public($publicKeyPem);
$verify = openssl_verify($canonicalJson, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
if ($verify !== 1) {
    fwrite(STDERR, "Self-verification of signature failed!\n");
    exit(1);
}
echo "Signature successfully verified with public key!\n";

// 6. Write back module.json
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Updated module.json with checksum and signature.\n";

// 7. Create ZIP archive
if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create zip at {$zipPath}\n");
    exit(1);
}

// Add files
foreach ($iterator as $item) {
    if ($item->isFile()) {
        $real = $item->getRealPath();
        $relative = substr($real, strlen(realpath($pluginDir)) + 1);
        $relative = str_replace('\\', '/', $relative);
        $zip->addFile($real, $relative);
    }
}
$zip->close();
echo "hrm.zip created successfully (" . round(filesize($zipPath) / 1024, 2) . " KB).\n";
