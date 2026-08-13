<?php

namespace App\Console\Commands;

use App\Domain\Installation\InstallationService;
use Illuminate\Console\Command;

class InstallBusinessOs extends Command
{
    protected $signature = 'businessos:install
        {--site-name=HiddenLeaf BusinessOS}
        {--app-url=}
        {--environment=production}
        {--db-connection=pgsql}
        {--db-host=127.0.0.1}
        {--db-port=5432}
        {--db-database=}
        {--db-username=}
        {--db-password=}
        {--license-token=}
        {--license-domain=}
        {--admin-name=}
        {--admin-email=}
        {--admin-password=}
        {--modules=account,hrm,lead,taskly,pos,productservice,landingpage}
        {--language=en}
        {--currency=USD}
        {--timezone=UTC}
        {--storage-driver=local}';

    protected $description = 'Install and initialize HiddenLeaf BusinessOS using the production installer service';

    public function handle(InstallationService $installer): int
    {
        foreach (['app-url', 'db-database', 'db-username', 'license-token', 'license-domain', 'admin-name', 'admin-email', 'admin-password'] as $required) {
            if (! $this->option($required)) {
                $this->error("Missing required option --{$required}.");

                return self::FAILURE;
            }
        }

        $installer->install([
            'site_name' => $this->option('site-name'),
            'app_url' => $this->option('app-url'),
            'app_environment' => $this->option('environment'),
            'db_connection' => $this->option('db-connection'),
            'db_host' => $this->option('db-host'),
            'db_port' => (int) $this->option('db-port'),
            'db_database' => $this->option('db-database'),
            'db_username' => $this->option('db-username'),
            'db_password' => $this->option('db-password'),
            'license_token' => $this->option('license-token'),
            'license_domain' => $this->option('license-domain'),
            'admin_name' => $this->option('admin-name'),
            'admin_email' => $this->option('admin-email'),
            'admin_password' => $this->option('admin-password'),
            'modules' => array_values(array_filter(array_map('trim', explode(',', $this->option('modules'))))),
            'language' => $this->option('language'),
            'currency' => strtoupper($this->option('currency')),
            'timezone' => $this->option('timezone'),
            'storage_driver' => $this->option('storage-driver'),
        ]);

        $this->info('HiddenLeaf BusinessOS installation completed.');

        return self::SUCCESS;
    }
}
