<?php

namespace Tests\Feature\InstallerAndLicensing;

use App\Models\Setting;
use App\Models\User;
use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LicenseTestKeys;
use Tests\TestCase;

class InstallerLicensingSuiteTest extends TestCase
{
    use DatabaseMigrations;

    private string $originalDatabaseConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDatabaseConnection = config('database.default');
        $keys = LicenseTestKeys::get();
        config()->set('licensing.private_key', $keys['private']);
        config()->set('licensing.public_key', $keys['public']);
        config()->set('installer.persist_environment', false);
        $this->app->instance(LicenseManager::class, new LicenseManager($keys['private'], $keys['public'], 14));
        if (File::exists(storage_path('installed'))) {
            File::delete(storage_path('installed'));
        }
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('installed'))) {
            File::delete(storage_path('installed'));
        }
        DB::purge('installer');
        config()->set('database.default', $this->originalDatabaseConnection);
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
            'app_url' => 'https://app.hiddenleaf-corp.com',
            'app_environment' => 'production',
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
            'license_token' => app(LicenseManager::class)->createSignedToken([
                'domain' => 'app.hiddenleaf-corp.com',
                'entitlements' => ['modules' => ['account', 'productservice']],
                'exp' => time() + 86400,
            ]),
            'license_domain' => 'app.hiddenleaf-corp.com',
            'admin_name' => 'Master Administrator',
            'admin_email' => 'admin@hiddenleaf-corp.com',
            'admin_password' => 'supersecurepassword123',
            'admin_password_confirmation' => 'supersecurepassword123',
            'modules' => ['account', 'productservice'],
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'storage_driver' => 'local',
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
        $this->assertDatabaseHas('settings', ['key' => 'installed_modules', 'value' => '["account","productservice"]']);
        $this->assertDatabaseHas('plans', ['name' => 'Free', 'free_plan' => true]);

        $this->post('/install', $setupData)->assertNotFound();
    }

    public function test_installer_rejects_an_invalid_offline_license_token(): void
    {
        $this->post('/install', [
            'site_name' => 'Invalid Install',
            'app_url' => 'https://invalid.example',
            'app_environment' => 'production',
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
            'license_token' => 'tampered-token',
            'license_domain' => 'invalid.example',
            'admin_name' => 'Administrator',
            'admin_email' => 'admin@invalid.example',
            'admin_password' => 'supersecurepassword123',
            'admin_password_confirmation' => 'supersecurepassword123',
            'modules' => ['account'],
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'storage_driver' => 'local',
        ])->assertSessionHasErrors('installation');

        $this->assertFalse(File::exists(storage_path('installed')));
    }

    public function test_installer_rejects_domain_mismatch_and_invalid_admin_input(): void
    {
        $token = app(LicenseManager::class)->createSignedToken([
            'domain' => 'licensed.hiddenleaf.test',
            'exp' => time() + 86400,
        ]);

        $this->post('/install', [
            'site_name' => 'Domain mismatch',
            'app_url' => 'https://other.hiddenleaf.test',
            'app_environment' => 'production',
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
            'license_token' => $token,
            'license_domain' => 'other.hiddenleaf.test',
            'admin_name' => 'Admin',
            'admin_email' => 'not-an-email',
            'admin_password' => 'short',
            'admin_password_confirmation' => 'different',
            'modules' => ['account'],
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'storage_driver' => 'local',
        ])->assertSessionHasErrors(['admin_email', 'admin_password']);

        $this->assertFalse(File::exists(storage_path('installed')));
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);

        $this->post('/install', [
            'site_name' => 'Domain mismatch',
            'app_url' => 'https://other.hiddenleaf.test',
            'app_environment' => 'production',
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
            'license_token' => $token,
            'license_domain' => 'other.hiddenleaf.test',
            'admin_name' => 'Administrator',
            'admin_email' => 'admin@other.hiddenleaf.test',
            'admin_password' => 'a-secure-password-123',
            'admin_password_confirmation' => 'a-secure-password-123',
            'modules' => ['account'],
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'storage_driver' => 'local',
        ])->assertSessionHasErrors('installation');

        $this->assertFalse(File::exists(storage_path('installed')));
    }

    public function test_database_probe_fails_safely_without_reflecting_credentials(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is required for the invalid credential probe.');
        }

        $secret = 'do-not-reflect-this-password';
        $response = $this->postJson('/install/test-db', [
            'db_connection' => 'pgsql',
            'db_host' => '127.0.0.1',
            'db_port' => 1,
            'db_database' => 'missing',
            'db_username' => 'invalid',
            'db_password' => $secret,
        ]);

        $response->assertUnprocessable()->assertJson(['success' => false]);
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertFalse(File::exists(storage_path('installed')));
    }

    public function test_updater_rejects_unsigned_legacy_requests(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post('/update');
        $response->assertSessionHasErrors('manifest');
        $this->assertDatabaseMissing('settings', ['key' => 'app_version', 'value' => '1.1.0']);
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
