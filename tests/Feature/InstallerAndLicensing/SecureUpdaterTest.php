<?php

namespace Tests\Feature\InstallerAndLicensing;

use App\Domain\Updates\SafeUpdateArchive;
use App\Domain\Updates\UpdateManifestService;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\UpdateHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\LicenseTestKeys;
use Tests\TestCase;
use ZipArchive;

class SecureUpdaterTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryRoot = storage_path('framework/testing/updater-'.uniqid());
        File::ensureDirectoryExists($this->temporaryRoot);
        config([
            'updater.public_key' => LicenseTestKeys::get()['public'],
            'updater.application_root' => $this->temporaryRoot.'/application',
            'updater.backup_directory' => $this->temporaryRoot.'/backups',
            'updater.staging_directory' => $this->temporaryRoot.'/staging',
            'updater.backup_database' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);
        parent::tearDown();
    }

    public function test_update_routes_require_a_superadmin(): void
    {
        $this->get('/update')->assertRedirect('/login');

        $ordinaryUser = User::factory()->create(['role' => 'company_admin']);
        $organization = Organization::factory()->create(['owner_id' => $ordinaryUser->id]);
        $organization->members()->attach($ordinaryUser, ['role' => 'owner']);
        $this->actingAs($ordinaryUser)->get('/update')->assertForbidden();
        $this->actingAs($ordinaryUser)->post('/update/check')->assertForbidden();
    }

    public function test_manifest_signature_and_archive_paths_are_verified(): void
    {
        $manifest = $this->signedManifest(str_repeat('a', 64));
        app(UpdateManifestService::class)->verify($manifest);

        $manifest['version'] = '9.9.9';
        $this->expectException(RuntimeException::class);
        app(UpdateManifestService::class)->verify($manifest);
    }

    public function test_archive_rejects_directory_traversal(): void
    {
        $archive = $this->temporaryRoot.'/unsafe.zip';
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('../outside.php', '<?php');
        $zip->close();

        $this->expectException(RuntimeException::class);
        app(SafeUpdateArchive::class)->extract($archive, $this->temporaryRoot.'/unsafe-extract');
    }

    public function test_signed_update_installs_manifest_version_and_can_roll_back(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        Setting::create(['key' => 'app_version', 'value' => '1.0.0', 'workspace_id' => null, 'created_by' => $user->id]);
        File::ensureDirectoryExists($this->temporaryRoot.'/application');
        File::put($this->temporaryRoot.'/application/existing.txt', 'before');

        $archive = $this->package([
            'existing.txt' => 'after',
            'new/delivered.txt' => 'new file',
        ]);
        $manifest = $this->signedManifest(hash_file('sha256', $archive));
        Http::fake(['https://updates.hiddenleaf.test/package.zip' => Http::response(File::get($archive))]);

        $this->actingAs($user)->post('/update', ['manifest' => $manifest])->assertSessionHasNoErrors();

        $history = UpdateHistory::sole();
        $this->assertSame('completed', $history->status, $history->error_message ?? 'Update did not complete.');
        $this->assertSame('2.0.0', admin_setting('app_version'));
        $this->assertSame('after', File::get($this->temporaryRoot.'/application/existing.txt'));
        $this->assertSame('new file', File::get($this->temporaryRoot.'/application/new/delivered.txt'));

        $this->actingAs($user)->post("/update/{$history->id}/rollback")->assertSessionHasNoErrors();

        $this->assertSame('rolled_back', $history->refresh()->status);
        $this->assertSame('1.0.0', admin_setting('app_version'));
        $this->assertSame('before', File::get($this->temporaryRoot.'/application/existing.txt'));
        $this->assertFileDoesNotExist($this->temporaryRoot.'/application/new/delivered.txt');
    }

    public function test_checksum_mismatch_fails_without_changing_application_files(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $archive = $this->package(['should-not-exist.txt' => 'unsafe']);
        Http::fake(['https://updates.hiddenleaf.test/package.zip' => Http::response(File::get($archive))]);

        $response = $this->actingAs($user)->post('/update', ['manifest' => $this->signedManifest(str_repeat('0', 64))]);
        $response->assertServerError();

        $this->assertSame('failed', UpdateHistory::sole()->status);
        $this->assertFileDoesNotExist($this->temporaryRoot.'/application/should-not-exist.txt');
    }

    private function signedManifest(string $sha256): array
    {
        $manifest = [
            'version' => '2.0.0',
            'channel' => 'stable',
            'package_url' => 'https://updates.hiddenleaf.test/package.zip',
            'sha256' => $sha256,
            'minimum_php' => '8.0.0',
            'minimum_laravel' => '12.0.0',
        ];
        ksort($manifest);
        openssl_sign(
            json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $signature,
            LicenseTestKeys::get()['private'],
            OPENSSL_ALGO_SHA256,
        );
        $manifest['signature'] = base64_encode($signature);

        return $manifest;
    }

    private function package(array $files): string
    {
        $archive = $this->temporaryRoot.'/package-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        foreach ($files as $name => $contents) {
            $zip->addFromString('payload/'.$name, $contents);
        }
        $zip->close();

        return $archive;
    }
}
