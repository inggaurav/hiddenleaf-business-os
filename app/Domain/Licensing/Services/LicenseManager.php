<?php

namespace HiddenLeaf\Domain\Licensing\Services;

class LicenseManager
{
    protected string $secretKey;

    protected int $gracePeriodDays;

    public function __construct(string $secretKey = 'hiddenleaf_secure_license_signing_secret', int $gracePeriodDays = 14)
    {
        $this->secretKey = $secretKey;
        $this->gracePeriodDays = $gracePeriodDays;
    }

    public function generateLicenseKey(string $productAlias, string $licenseType = 'REGULAR'): string
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

    public function verifySignedToken(string $token): array
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
