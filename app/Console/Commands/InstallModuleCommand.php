<?php

namespace App\Console\Commands;

use App\Services\ModuleManager;
use Illuminate\Console\Command;

class InstallModuleCommand extends Command
{
    protected $signature = 'businessos:module:install {archive : Path to the module zip package}';

    protected $description = 'Install and activate a BusinessOS module zip package';

    public function handle(ModuleManager $moduleManager): int
    {
        $archive = $this->argument('archive');

        if (! file_exists($archive)) {
            $this->error("Module package archive not found: {$archive}");

            return self::FAILURE;
        }

        $this->info("Installing module package: {$archive}...");

        $result = $moduleManager->installFromZip(realpath($archive));

        if (! ($result['success'] ?? false)) {
            $this->error("Installation failed: " . ($result['message'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $this->info($result['message'] ?? 'Module installed successfully.');

        $this->info('Running database migrations...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Module installation completed successfully.');

        return self::SUCCESS;
    }
}
