<?php

namespace HiddenLeaf\Domain\Licensing\Services;

use Illuminate\Container\Container;
use LogicException;

class LicenseManager
{
    private ?string $privateKey;

    private ?string $publicKey;

    private int $gracePeriodDays;

    public function __construct(?string $privateKey = null, ?string $publicKey = null, ?int $gracePeriodDays = null)
    {
        $this->privateKey = $this->normalizeKey($privateKey ?? $this->configValue('licensing.private_key'));
        $this->publicKey = $this->normalizeKey($publicKey ?? $this->configValue('licensing.public_key'));

        if (! $this->publicKey && $this->privateKey) {
            $resource = openssl_pkey_get_private($this->privateKey);
            $details = $resource ? openssl_pkey_get_details($resource) : false;
            $this->publicKey = $details['key'] ?? null;
        }

        $this->gracePeriodDays = $gracePeriodDays ?? (int) ($this->configValue('licensing.grace_period_days', 14));
    }

    public static function generateKeyPair(int $bits = 2048): array
    {
        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $candidates = [
            getenv('OPENSSL_CONF'),
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'openssl.cnf',
            'C:\\Program Files\\Common Files\\SSL\\openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_file($candidate)) {
                $config['config'] = $candidate;
                break;
            }
        }

        $resource = openssl_pkey_new($config);

        if (! $resource || ! openssl_pkey_export($resource, $privateKey, null, $config)) {
            throw new LogicException('Unable to generate the licensing signing key pair.');
        }

        $details = openssl_pkey_get_details($resource);

        return ['private' => $privateKey, 'public' => $details['key']];
    }

    public function generateLicenseKey(string $productAlias, string $licenseType = 'SAAS'): string
    {
        $prefix = strtoupper(substr($productAlias, 0, 3));
        $chunks = str_split(strtoupper(bin2hex(random_bytes(10))), 4);

        return "HL-{$prefix}-".strtoupper($licenseType).'-'.implode('-', $chunks);
    }

    public function createSignedToken(array $payload): string
    {
        if (! $this->privateKey) {
            throw new LogicException('LICENSE_SERVER_PRIVATE_KEY is not configured on the licensing authority.');
        }

        $now = time();
        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['nbf'] = $payload['nbf'] ?? $now;
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->configValue('licensing.key_id', 'primary')];
        $unsigned = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)).'.'
            .$this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        if (! openssl_sign($unsigned, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new LogicException('Unable to sign the license entitlement token.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    public function verifySignedToken(string $token, ?string $domain = null): array
    {
        if (! $this->publicKey) {
            return ['valid' => false, 'error' => 'LICENSING_PUBLIC_KEY is not configured.'];
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid token structure'];
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || ! is_array($payload) || $signature === '') {
            return ['valid' => false, 'error' => 'Invalid token payload or algorithm'];
        }

        $verificationKey = @openssl_pkey_get_public($this->publicKey);
        if (! $verificationKey) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }

        $verification = openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $verificationKey, OPENSSL_ALGO_SHA256);
        if ($verification !== 1) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }

        if (isset($payload['nbf']) && time() < (int) $payload['nbf']) {
            return ['valid' => false, 'error' => 'License token is not active yet'];
        }

        if ($domain && isset($payload['domain']) && ! $this->domainMatches((string) $payload['domain'], $domain)) {
            return ['valid' => false, 'error' => "Domain mismatch: license is bound to {$payload['domain']}."];
        }

        if (($payload['license_status'] ?? 'active') !== 'active' || ($payload['activation_status'] ?? 'active') !== 'active') {
            return ['valid' => false, 'error' => 'License or activation is not active'];
        }

        if (isset($payload['exp']) && time() > (int) $payload['exp']) {
            $graceDays = (int) ($payload['grace_period_days'] ?? $this->gracePeriodDays);
            if (time() <= ((int) $payload['exp'] + ($graceDays * 86400))) {
                return ['valid' => true, 'grace_period' => true, 'payload' => $payload];
            }

            return ['valid' => false, 'error' => 'License expired'];
        }

        return ['valid' => true, 'grace_period' => false, 'payload' => $payload];
    }

    private function normalizeKey(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        if (is_file($key)) {
            $key = file_get_contents($key) ?: null;
        }

        return $key ? str_replace('\\n', "\n", trim($key)) : null;
    }

    private function configValue(string $key, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        return $container && $container->bound('config') ? $container->make('config')->get($key, $default) : $default;
    }

    private function domainMatches(string $allowed, string $actual): bool
    {
        $allowed = strtolower(trim($allowed));
        $actual = strtolower(trim($actual));

        if ($allowed === '*' || $allowed === $actual) {
            return true;
        }

        return str_starts_with($allowed, '*.') && str_ends_with($actual, substr($allowed, 1));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
