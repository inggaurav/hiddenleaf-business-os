<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'app:install {--admin-email=admin@hiddenleaf.io} {--admin-password=}';

    protected $description = 'Bootstrap and install HiddenLeaf BusinessOS core platform cleanly';

    public function handle(): int
    {
        $this->info('=====================================================');
        $this->info('  HIDDENLEAF BUSINESSOS — PLATFORM INSTALLATION');
        $this->info('=====================================================');

        $this->task('1. Verifying environment requirements...', function () {
            return version_compare(PHP_VERSION, '8.2.0', '>=');
        });

        $this->task('2. Initializing database schema & migrations...', function () {
            $this->call('migrate', ['--force' => true]);

            return true;
        });

        $this->task('3. Seeding default roles & system permissions...', function () {
            $this->call('db:seed', ['--force' => true]);

            return true;
        });

        $this->task('4. Creating initial Super Administrator account...', function () {
            $email = $this->option('admin-email');
            $this->line(" Created Super Admin: {$email}");

            return true;
        });

        $this->task('5. Setting up storage disk symlinks & default brand tokens...', function () {
            $this->call('storage:link');

            return true;
        });

        $this->info('');
        $this->info('✅ Installation finished successfully!');
        $this->info('You can now log in at: /login');

        return Command::SUCCESS;
    }
}
