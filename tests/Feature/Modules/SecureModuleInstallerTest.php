<?php

namespace Tests\Feature\Modules;

use App\Domain\Modules\SecureModuleInstaller;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Support\LicenseTestKeys;
use Tests\TestCase;
use ZipArchive;

class SecureModuleInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/modules-'.uniqid());
        File::ensureDirectoryExists($this->root);
        config(['modules.directory' => $this->root.'/installed', 'modules.public_key' => LicenseTestKeys::get()['public'], 'modules.core_version' => '1.0.0']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_signed_module_package_installs_safely(): void
    {
        $archive = $this->package(['src/module.php' => '<?php return true;']);
        $manifest = app(SecureModuleInstaller::class)->install($archive);
        $this->assertSame('sample', $manifest['alias']);
        $this->assertFileExists($this->root.'/installed/sample/src/module.php');
    }

    public function test_traversal_and_tampered_signature_are_rejected(): void
    {
        $unsafe = $this->root.'/unsafe.zip';
        $zip = new ZipArchive;
        $zip->open($unsafe, ZipArchive::CREATE);
        $zip->addFromString('../escape.php', 'bad');
        $zip->addFromString('module.json', '{}');
        $zip->close();
        $this->expectException(RuntimeException::class);
        app(SecureModuleInstaller::class)->install($unsafe);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $archive = $this->package(['src/module.php' => 'changed']);
        $zip = new ZipArchive;
        $zip->open($archive);
        $manifest = json_decode($zip->getFromName('module.json'), true);
        $manifest['version'] = '9.9.9';
        $zip->addFromString('module.json', json_encode($manifest));
        $zip->close();
        $this->expectException(RuntimeException::class);
        app(SecureModuleInstaller::class)->install($archive);
    }

    private function package(array $files): string
    {
        $hashes = [];
        foreach ($files as $name => $content) {
            $hashes[$name] = hash('sha256', $content);
        }ksort($hashes);
        $manifest = ['id' => 'com.hiddenleaf.sample', 'name' => 'Sample', 'alias' => 'sample', 'version' => '1.0.0', 'minimum_core' => '1.0.0', 'dependencies' => [], 'checksum' => hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
        ksort($manifest);
        openssl_sign(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $signature, LicenseTestKeys::get()['private'], OPENSSL_ALGO_SHA256);
        $manifest['signature'] = base64_encode($signature);
        $archive = $this->root.'/module-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode($manifest));
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }$zip->close();

        return $archive;
    }
}
