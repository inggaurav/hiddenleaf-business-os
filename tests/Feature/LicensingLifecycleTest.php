<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\LicenseCustomer;
use App\Models\LicenseDomain;
use App\Models\LicenseEntitlement;
use App\Models\LicenseKey;
use App\Models\LicenseProduct;
use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\LicenseTestKeys;
use Tests\TestCase;

class LicensingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private LicenseManager $manager;

    private string $plainTextKey = 'HL-BUS-ENTERPRISE-AAAA-BBBB-CCCC-DDDD';

    protected function setUp(): void
    {
        parent::setUp();
        $keys = LicenseTestKeys::get();
        config()->set('licensing.private_key', $keys['private']);
        config()->set('licensing.public_key', $keys['public']);
        $this->manager = new LicenseManager($keys['private'], $keys['public'], 7);
        $this->app->instance(LicenseManager::class, $this->manager);
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload('HL-UNKNOWN'))
            ->assertNotFound()
            ->assertJson(['error' => 'Unknown license key.']);
    }

    public function test_activation_uses_database_entitlements_and_supports_offline_verification(): void
    {
        $this->createLicense();

        $response = $this->postJson('/api/v1/licensing/activate', $this->activationPayload())
            ->assertOk()
            ->assertJsonPath('entitlements.modules.0', 'core')
            ->assertJsonPath('entitlements.limits.max_users', 25);

        $token = $response->json('signed_token');
        $this->assertTrue($this->manager->verifySignedToken($token, 'app.hiddenleaf.test')['valid']);
        $this->assertDatabaseHas('license_activations', ['domain' => 'app.hiddenleaf.test', 'status' => 'active']);
        $this->assertDatabaseHas('licensing_events', ['event' => 'activated']);
    }

    public function test_wrong_domain_and_second_activation_are_rejected(): void
    {
        $license = $this->createLicense(activationLimit: 1, domains: ['app.hiddenleaf.test', 'second.hiddenleaf.test']);

        $this->postJson('/api/v1/licensing/activate', $this->activationPayload(domain: 'pirate.example'))
            ->assertForbidden();
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload())->assertOk();
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload(
            domain: 'second.hiddenleaf.test',
            installationId: (string) Str::uuid(),
        ))->assertStatus(409)->assertJson(['error' => 'Activation limit reached.']);

        $this->assertSame(1, $license->activations()->where('status', 'active')->count());
    }

    public function test_revoked_expired_and_suspended_licenses_are_rejected(): void
    {
        $license = $this->createLicense();
        $license->keys()->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload())->assertForbidden();

        $license->keys()->update(['status' => 'active', 'revoked_at' => null]);
        $license->update(['expires_at' => now()->subDay()]);
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload())->assertForbidden();

        $license->update(['expires_at' => now()->addMonth(), 'status' => 'suspended']);
        $this->postJson('/api/v1/licensing/activate', $this->activationPayload())->assertForbidden();
    }

    public function test_deactivation_is_persisted_and_invalidates_online_validation(): void
    {
        $this->createLicense();
        $token = $this->postJson('/api/v1/licensing/activate', $this->activationPayload())->json('signed_token');

        $this->postJson('/api/v1/licensing/deactivate', [
            'license_key' => $this->plainTextKey,
            'domain' => 'app.hiddenleaf.test',
            'installation_id' => '11111111-1111-4111-8111-111111111111',
        ])->assertOk()->assertJson(['status' => 'deactivated']);

        $this->assertDatabaseHas('license_activations', ['status' => 'deactivated']);
        $this->postJson('/api/v1/licensing/validate', ['token' => $token, 'domain' => 'app.hiddenleaf.test'])
            ->assertForbidden()
            ->assertJson(['valid' => false]);
    }

    public function test_tampering_invalid_signature_and_offline_grace_period(): void
    {
        $token = $this->manager->createSignedToken(['domain' => 'app.hiddenleaf.test', 'exp' => time() - 3600, 'grace_period_days' => 7]);
        $grace = $this->manager->verifySignedToken($token, 'app.hiddenleaf.test');
        $this->assertTrue($grace['valid']);
        $this->assertTrue($grace['grace_period']);
        $this->assertFalse($this->manager->verifySignedToken($token.'tampered')['valid']);

        $untrustedPublic = str_replace('MIIBIjAN', 'MIIBJjAN', LicenseTestKeys::get()['public']);
        $this->assertFalse((new LicenseManager(null, $untrustedPublic))->verifySignedToken($token)['valid']);
    }

    private function createLicense(int $activationLimit = 1, array $domains = ['app.hiddenleaf.test']): License
    {
        $customer = LicenseCustomer::create(['name' => 'Acme Corp', 'email' => uniqid('license-', true).'@test.local', 'status' => 'active']);
        $product = LicenseProduct::firstOrCreate(['alias' => 'business-os'], ['name' => 'BusinessOS']);
        $license = License::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'activation_limit' => $activationLimit,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'grace_period_days' => 7,
        ]);
        LicenseKey::create(['license_id' => $license->id, 'key_hash' => LicenseKey::digest($this->plainTextKey), 'key_prefix' => 'HL-BUS-ENTERPRISE', 'status' => 'active']);
        foreach ($domains as $domain) {
            LicenseDomain::create(['license_id' => $license->id, 'domain' => $domain, 'is_active' => true]);
        }
        LicenseEntitlement::create(['license_id' => $license->id, 'key' => 'modules', 'value' => ['core', 'account', 'hrm']]);
        LicenseEntitlement::create(['license_id' => $license->id, 'key' => 'limits', 'value' => ['max_users' => 25, 'max_workspaces' => 5]]);

        return $license;
    }

    private function activationPayload(string $key = '', string $domain = 'app.hiddenleaf.test', string $installationId = '11111111-1111-4111-8111-111111111111'): array
    {
        return ['license_key' => $key ?: $this->plainTextKey, 'domain' => $domain, 'product_alias' => 'business-os', 'installation_id' => $installationId];
    }
}
