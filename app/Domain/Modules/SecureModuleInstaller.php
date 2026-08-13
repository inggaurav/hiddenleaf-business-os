<?php

namespace App\Domain\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SecureModuleInstaller
{
    public function install(string $archive): array
    {
        if (! is_file($archive) || filesize($archive) > config('modules.maximum_bytes')) {
            throw new RuntimeException('Module package is missing or exceeds the size limit.');
        }$zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open module package.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', $zip->getNameIndex($i));
                $has = $zip->getExternalAttributesIndex($i, $ops, $attrs);
                $mode = $has ? (($attrs >> 16) & 0170000) : 0;
                if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name) || in_array('..', explode('/', $name), true) || $mode === 0120000) {
                    throw new RuntimeException('Module package contains an unsafe path or symbolic link.');
                }
            }$manifestRaw = $zip->getFromName('module.json');
            if ($manifestRaw === false) {
                throw new RuntimeException('Module manifest is missing.');
            }$manifest = json_decode($manifestRaw, true, flags: JSON_THROW_ON_ERROR);
            $this->verify($manifest);
            $stage = storage_path('app/modules/staging/'.Str::uuid());
            File::ensureDirectoryExists($stage);
            if (! $zip->extractTo($stage)) {
                throw new RuntimeException('Unable to extract module package.');
            }
        } finally {
            $zip->close();
        }
        $hashes = [];
        foreach (File::allFiles($stage) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($relative !== 'module.json') {
                $hashes[$relative] = hash_file('sha256', $file->getPathname());
            }
        }ksort($hashes);
        $checksum = hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (! hash_equals(strtolower($manifest['checksum']), $checksum)) {
            File::deleteDirectory($stage);
            throw new RuntimeException('Module package checksum is invalid.');
        }
        $alias = $manifest['alias'];
        $destination = rtrim(config('modules.directory'), '/\\').DIRECTORY_SEPARATOR.$alias;
        if (is_dir($destination)) {
            File::deleteDirectory($stage);
            throw new RuntimeException('A module with this alias is already installed.');
        }File::ensureDirectoryExists(dirname($destination));
        if (! File::moveDirectory($stage, $destination)
            && (! File::copyDirectory($stage, $destination) || ! File::deleteDirectory($stage))) {
            File::deleteDirectory($destination);
            throw new RuntimeException('Unable to install module files.');
        }

        return $manifest;
    }

    private function verify(array $manifest): void
    {
        foreach (['id', 'name', 'alias', 'version', 'minimum_core', 'dependencies', 'checksum', 'signature'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new RuntimeException("Module manifest field {$field} is missing.");
            }
        }if (! preg_match('/^[a-z][a-z0-9-]{1,63}$/', $manifest['alias']) || ! is_array($manifest['dependencies']) || ! preg_match('/^[a-f0-9]{64}$/i', $manifest['checksum'])) {
            throw new RuntimeException('Module manifest is invalid.');
        }if (version_compare(config('modules.core_version'), $manifest['minimum_core'], '<')) {
            throw new RuntimeException('Module requires a newer BusinessOS core.');
        }$canonical = $manifest;
        unset($canonical['signature']);
        ksort($canonical);
        $key = config('modules.public_key');
        if (is_file((string) $key)) {
            $key = file_get_contents($key);
        }$signature = base64_decode($manifest['signature'], true);
        $public = $key ? openssl_pkey_get_public(str_replace('\\n', "\n", $key)) : false;
        if (! $signature || ! $public || openssl_verify(json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $signature, $public, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Module signature is invalid.');
        }
    }
}
