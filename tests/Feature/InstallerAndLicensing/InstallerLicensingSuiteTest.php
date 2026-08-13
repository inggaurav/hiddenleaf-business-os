<?php

namespace Tests\Feature\InstallerAndLicensing;

use App\Models\Setting;
use App\Models\User;
use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LicenseTestKeys;
use Tests\TestCase;

class InstallerLicensingSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (File::exists(storage_path('installed'))) {
            File::delete(storage_path('installed'));
        }
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('installed'))) {
            File::delete(storage_path('installed'));
        }
        parent::tearDown();
    }

    public function test_installer_requirements_view(): void
    {
        $response = $this->get('/install');
        $response->assertStatus(200);
    }

    public function test_real_multi_step_installation_lifecycle(): void
    {
        $this->assertFalse(File::exists(storage_path('installed')));

        $setupData = [
            'site_name' => 'HiddenLeaf Enterprise Production',
            'admin_name' => 'Master Administrator',
            'admin_email' => 'admin@hiddenleaf-corp.com',
            'admin_password' => 'supersecurepassword123',
            'admin_password_confirmation' => 'supersecurepassword123',
        ];

        $response = $this->post('/install', $setupData);

        $response->assertRedirect('/login');
        $this->assertTrue(File::exists(storage_path('installed')));

        // Check Super Admin user created
        $admin = User::where('email', 'admin@hiddenleaf-corp.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('super_admin', $admin->role);
        $this->assertTrue(Hash::check('supersecurepassword123', $admin->password));

        // Check site settings saved
        $setting = Setting::where('key', 'site_name')->whereNull('workspace_id')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('HiddenLeaf Enterprise Production', $setting->value);
    }

    public function test_updater_lifecycle(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post('/update');
        $response->assertSessionHas('success');

        $versionSetting = Setting::where('key', 'app_version')->whereNull('workspace_id')->first();
        $this->assertNotNull($versionSetting);
        $this->assertEquals('1.1.0', $versionSetting->value);
    }

    public function test_commercial_licensing_verification_and_domain_binding(): void
    {
        $keys = LicenseTestKeys::get();
        $licenseManager = new LicenseManager($keys['private'], $keys['public'], 14);

        // 1. Generate license key
        $key = $licenseManager->generateLicenseKey('saas', 'ENTERPRISE');
        $this->assertStringStartsWith('HL-SAA-ENTERPRISE-', $key);

        // 2. Create signed token for domain
        $payload = [
            'license_key' => $key,
            'domain' => 'app.hiddenleaf.test',
            'product' => 'business-os',
            'exp' => time() + 86400,
        ];
        $signedToken = $licenseManager->createSignedToken($payload);

        // 3. Verify valid token with matching domain
        $result = $licenseManager->verifySignedToken($signedToken, 'app.hiddenleaf.test');
        $this->assertTrue($result['valid']);
        $this->assertFalse($result['grace_period']);

        // 4. Verify invalid domain
        $mismatchResult = $licenseManager->verifySignedToken($signedToken, 'pirate-site.com');
        $this->assertFalse($mismatchResult['valid']);
        $this->assertStringContainsString('Domain mismatch', $mismatchResult['error']);

        // 5. Verify expired token within grace period
        $expiredPayload = [
            'license_key' => $key,
            'domain' => 'app.hiddenleaf.test',
            'exp' => time() - 3600, // Expired 1 hour ago
        ];
        $expiredToken = $licenseManager->createSignedToken($expiredPayload);
        $graceResult = $licenseManager->verifySignedToken($expiredToken, 'app.hiddenleaf.test');
        $this->assertTrue($graceResult['valid']);
        $this->assertTrue($graceResult['grace_period']);
    }
}
