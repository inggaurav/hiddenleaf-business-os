<?php

namespace App\Domain\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class UpdateManifestService
{
    public function fetch(?string $channel = null): array
    {
        $url = config('updater.manifest_url');
        if (! $url || ! Str::startsWith($url, 'https://')) {
            throw new RuntimeException('UPDATE_MANIFEST_URL must be a configured HTTPS endpoint.');
        }

        $manifest = Http::timeout(15)->acceptJson()->get($url, ['channel' => $channel ?? config('updater.channel')])->throw()->json();
        if (! is_array($manifest)) {
            throw new RuntimeException('The update service returned an invalid manifest.');
        }

        return $this->verify($manifest, $channel);
    }

    public function verify(array $manifest, ?string $expectedChannel = null): array
    {
        foreach (['version', 'channel', 'package_url', 'sha256', 'signature', 'minimum_php', 'minimum_laravel'] as $field) {
            if (! isset($manifest[$field]) || ! is_string($manifest[$field]) || $manifest[$field] === '') {
                throw new RuntimeException("Update manifest field {$field} is missing.");
            }
        }

        $channel = $expectedChannel ?? config('updater.channel');
        if ($manifest['channel'] !== $channel || ! in_array($channel, config('updater.allowed_channels'), true)) {
            throw new RuntimeException('The update manifest release channel is not allowed.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/i', $manifest['sha256'])) {
            throw new RuntimeException('The update manifest SHA-256 checksum is invalid.');
        }
        foreach (['version', 'minimum_php', 'minimum_laravel'] as $versionField) {
            if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest[$versionField])) {
                throw new RuntimeException("Update manifest field {$versionField} is not a valid semantic version.");
            }
        }
        if (! Str::startsWith($manifest['package_url'], 'https://')) {
            throw new RuntimeException('Update packages must be downloaded over HTTPS.');
        }

        $signature = base64_decode($manifest['signature'], true);
        $publicKey = $this->key(config('updater.public_key'));
        $verificationKey = $publicKey ? @openssl_pkey_get_public($publicKey) : false;
        $canonical = $manifest;
        unset($canonical['signature']);
        ksort($canonical);

        if (! $signature || ! $verificationKey || openssl_verify(json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $signature, $verificationKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The update manifest signature is invalid.');
        }

        return $manifest;
    }

    private function key(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        if (is_file($key)) {
            return file_get_contents($key) ?: null;
        }

        return str_replace('\\n', "\n", $key);
    }
}
