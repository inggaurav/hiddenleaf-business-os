<?php

namespace HiddenLeaf\Domain\Licensing\Services;

class LicenseManager
{
    protected string $secretKey;

    protected int $gracePeriodDays;

    public function __construct(?string $secretKey = null, int $gracePeriodDays = 14)
    {
        $this->secretKey = $secretKey ?: (config('app.key') ?: env('APP_KEY', 'hiddenleaf_production_license_master_key_2026'));
        $this->gracePeriodDays = $gracePeriodDays;
    }

    public function generateLicenseKey(string $productAlias, string $licenseType = 'SAAS'): string
    {
        $prefix = strtoupper(substr($productAlias, 0, 3));
        $unique = strtoupper(bin2hex(random_bytes(8)));
        $chunks = str_split($unique, 4);

        return "HL-{$prefix}-{$licenseType}-".implode('-', $chunks);
    }

    public function createSignedToken(array $payload): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload['iat'] = time();
        $payload['nbf'] = time();

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = hash_hmac('sha256', $base64Header.'.'.$base64Payload, $this->secretKey, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header.'.'.$base64Payload.'.'.$base64Signature;
    }

    public function verifySignedToken(string $token, ?string $domain = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid token structure'];
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $signature = hash_hmac('sha256', $base64Header.'.'.$base64Payload, $this->secretKey, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (! hash_equals($expectedSignature, $base64Signature)) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);

        if (! is_array($payload)) {
            return ['valid' => false, 'error' => 'Invalid payload format'];
        }

        // Domain verification if provided
        if ($domain && isset($payload['domain'])) {
            $allowedDomain = strtolower($payload['domain']);
            $currentDomain = strtolower($domain);
            if ($allowedDomain !== '*' && $allowedDomain !== 'localhost' && $allowedDomain !== $currentDomain) {
                return ['valid' => false, 'error' => "Domain mismatch: license is bound to {$payload['domain']}."];
            }
        }

        // Expiry check
        if (isset($payload['exp']) && time() > $payload['exp']) {
            $graceExpiry = $payload['exp'] + ($this->gracePeriodDays * 86400);
            if (time() <= $graceExpiry) {
                return ['valid' => true, 'grace_period' => true, 'payload' => $payload];
            }

            return ['valid' => false, 'error' => 'License expired'];
        }

        return ['valid' => true, 'grace_period' => false, 'payload' => $payload];
    }
}
