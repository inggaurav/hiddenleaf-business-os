<?php

namespace Tests\Feature;

use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\LicenseTestKeys;

class LicensingTest extends TestCase
{
    protected LicenseManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = LicenseTestKeys::get();
        $this->manager = new LicenseManager($keys['private'], $keys['public'], 7);
    }

    public function test_license_key_generation_format(): void
    {
        $key = $this->manager->generateLicenseKey('business_os', 'ENTERPRISE');
        $this->assertStringStartsWith('HL-BUS-ENTERPRISE-', $key);
    }

    public function test_valid_signed_token_verification(): void
    {
        $payload = [
            'license_key' => 'HL-BUS-REGULAR-1234-5678',
            'domain' => 'client.hiddenleaf.io',
            'exp' => time() + 3600,
        ];

        $token = $this->manager->createSignedToken($payload);
        $result = $this->manager->verifySignedToken($token);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['grace_period']);
        $this->assertEquals('client.hiddenleaf.io', $result['payload']['domain']);
    }

    public function test_grace_period_for_expired_license(): void
    {
        $payload = [
            'license_key' => 'HL-BUS-REGULAR-1234-5678',
            'domain' => 'client.hiddenleaf.io',
            'exp' => time() - 3600, // Expired 1 hour ago (within 7 day grace)
        ];

        $token = $this->manager->createSignedToken($payload);
        $result = $this->manager->verifySignedToken($token);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['grace_period']);
    }

    public function test_tampered_token_rejection(): void
    {
        $payload = ['license_key' => 'HL-TEST', 'exp' => time() + 3600];
        $token = $this->manager->createSignedToken($payload);
        $tamperedToken = $token.'bad';

        $result = $this->manager->verifySignedToken($tamperedToken);
        $this->assertFalse($result['valid']);
    }

    public function test_public_key_can_verify_offline_without_private_signing_key(): void
    {
        $keys = LicenseTestKeys::get();
        $server = new LicenseManager($keys['private'], $keys['public'], 7);
        $installation = new LicenseManager(null, $keys['public'], 7);
        $token = $server->createSignedToken(['domain' => 'client.hiddenleaf.io', 'exp' => time() + 3600]);

        $this->assertTrue($installation->verifySignedToken($token, 'client.hiddenleaf.io')['valid']);
    }

    public function test_token_signed_by_a_different_private_key_is_rejected(): void
    {
        $trusted = LicenseTestKeys::get();
        $token = (new LicenseManager($trusted['private'], $trusted['public']))
            ->createSignedToken(['exp' => time() + 3600]);
        $untrustedPublic = str_replace('MIIBIjAN', 'MIIBJjAN', $trusted['public']);

        $result = (new LicenseManager(null, $untrustedPublic))->verifySignedToken($token);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid signature', $result['error']);
    }
}
