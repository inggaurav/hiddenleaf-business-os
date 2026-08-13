<?php

namespace HiddenLeaf\Domain\Licensing\Services;

use App\Models\LicenseActivation;
use App\Models\LicenseInstallation;
use App\Models\LicenseKey;
use App\Models\LicensingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LicenseActivationService
{
    public function __construct(private LicenseManager $tokens) {}

    public function activate(array $input, Request $request): array
    {
        $key = LicenseKey::query()
            ->where('key_hash', LicenseKey::digest($input['license_key']))
            ->with(['license.customer', 'license.product', 'license.domains', 'license.entitlements'])
            ->first();

        if (! $key) {
            return $this->failure('Unknown license key.', 404);
        }

        $license = $key->license;
        $domain = $this->normalizeDomain($input['domain']);
        $now = now();

        if ($key->status !== 'active' || $key->revoked_at) {
            return $this->failure('License key has been revoked.', 403);
        }
        if ($license->status !== 'active' || $license->customer?->status !== 'active') {
            return $this->failure('License is '.$license->status.'.', 403);
        }
        if ($license->starts_at && $now->lt($license->starts_at)) {
            return $this->failure('License is not active yet.', 403);
        }
        if ($license->expires_at && $now->gt($license->expires_at)) {
            return $this->failure('License has expired.', 403);
        }
        if ($license->product?->alias !== $input['product_alias']) {
            return $this->failure('License does not belong to the requested product.', 403);
        }

        $allowedDomains = $license->domains->where('is_active', true)->pluck('domain');
        if ($allowedDomains->isNotEmpty() && ! $allowedDomains->contains(fn (string $allowed) => $this->domainMatches($allowed, $domain))) {
            return $this->failure('Domain is not authorized for this license.', 403);
        }

        return DB::transaction(function () use ($license, $key, $domain, $input, $request, $now) {
            $installation = LicenseInstallation::updateOrCreate(
                ['license_id' => $license->id, 'installation_uuid' => $input['installation_id']],
                ['metadata' => $input['metadata'] ?? null, 'last_seen_at' => $now],
            );

            $activation = LicenseActivation::where([
                'license_key_id' => $key->id,
                'installation_id' => $installation->id,
                'domain' => $domain,
            ])->first();

            $activeCount = LicenseActivation::where('license_id', $license->id)->where('status', 'active')->count();
            if ((! $activation || $activation->status !== 'active') && $activeCount >= $license->activation_limit) {
                return $this->failure('Activation limit reached.', 409);
            }

            $activation = LicenseActivation::updateOrCreate(
                ['license_key_id' => $key->id, 'installation_id' => $installation->id, 'domain' => $domain],
                ['license_id' => $license->id, 'status' => 'active', 'activated_at' => $now, 'deactivated_at' => null],
            );

            $entitlements = $license->entitlements->mapWithKeys(fn ($item) => [$item->key => $item->value])->all();
            $ttlExpiry = $now->copy()->addDays((int) config('licensing.token_ttl_days', 30));
            $expiry = $license->expires_at && $license->expires_at->lt($ttlExpiry) ? $license->expires_at : $ttlExpiry;
            $payload = [
                'license_id' => $license->id,
                'license_key_id' => $key->id,
                'activation_id' => $activation->id,
                'installation_id' => $installation->installation_uuid,
                'product_alias' => $license->product->alias,
                'domain' => $domain,
                'license_status' => $license->status,
                'activation_status' => $activation->status,
                'entitlements' => $entitlements,
                'grace_period_days' => $license->grace_period_days,
                'exp' => $expiry->timestamp,
            ];

            $this->event('activated', $license->id, $key->id, $activation->id, $request, ['domain' => $domain]);

            return ['ok' => true, 'status_code' => 200, 'status' => 'activated', 'signed_token' => $this->tokens->createSignedToken($payload), 'entitlements' => $entitlements];
        });
    }

    public function deactivate(array $input, Request $request): array
    {
        $key = LicenseKey::where('key_hash', LicenseKey::digest($input['license_key']))->first();
        if (! $key) {
            return $this->failure('Unknown license key.', 404);
        }

        $installation = LicenseInstallation::where('license_id', $key->license_id)
            ->where('installation_uuid', $input['installation_id'])->first();
        $activation = $installation ? LicenseActivation::where([
            'license_key_id' => $key->id,
            'installation_id' => $installation->id,
            'domain' => $this->normalizeDomain($input['domain']),
            'status' => 'active',
        ])->first() : null;

        if (! $activation) {
            return $this->failure('Active installation was not found.', 404);
        }

        $activation->update(['status' => 'deactivated', 'deactivated_at' => now()]);
        $this->event('deactivated', $key->license_id, $key->id, $activation->id, $request, ['domain' => $activation->domain]);

        return ['ok' => true, 'status_code' => 200, 'status' => 'deactivated'];
    }

    public function validateOnline(string $token, ?string $domain, Request $request): array
    {
        $verified = $this->tokens->verifySignedToken($token, $domain ? $this->normalizeDomain($domain) : null);
        if (! $verified['valid']) {
            return ['ok' => false, 'status_code' => 401] + $verified;
        }

        $payload = $verified['payload'];
        $activation = LicenseActivation::with('license')->find($payload['activation_id'] ?? null);
        if (! $activation || $activation->status !== 'active' || $activation->license->status !== 'active') {
            return $this->failure('License activation has been revoked or suspended.', 403, ['valid' => false]);
        }

        if ($activation->license->expires_at && now()->gt($activation->license->expires_at->copy()->addDays($activation->license->grace_period_days))) {
            return $this->failure('License has expired.', 403, ['valid' => false]);
        }

        $this->event('validated', $activation->license_id, $activation->license_key_id, $activation->id, $request);

        return ['ok' => true, 'status_code' => 200] + $verified;
    }

    private function event(string $event, int $licenseId, ?int $keyId, ?int $activationId, Request $request, array $payload = []): void
    {
        LicensingEvent::create(['license_id' => $licenseId, 'license_key_id' => $keyId, 'activation_id' => $activationId, 'event' => $event, 'payload' => $payload, 'ip_address' => $request->ip()]);
    }

    private function failure(string $message, int $status, array $extra = []): array
    {
        return ['ok' => false, 'status_code' => $status, 'error' => $message] + $extra;
    }

    private function normalizeDomain(string $domain): string
    {
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);

        return strtolower(rtrim((string) $host, '.'));
    }

    private function domainMatches(string $allowed, string $actual): bool
    {
        $allowed = strtolower($allowed);

        return $allowed === '*' || $allowed === $actual || (str_starts_with($allowed, '*.') && str_ends_with($actual, substr($allowed, 1)));
    }
}
