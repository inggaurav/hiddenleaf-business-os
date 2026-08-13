<?php

namespace App\Domain\Installation;

use App\Models\Language;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InstallationService
{
    public function __construct(
        private DatabaseConfigurator $database,
        private EnvironmentFileWriter $environment,
        private LicenseManager $licenses,
    ) {}

    public function install(array $input): User
    {
        $lock = config('installer.lock_file');
        if (is_file($lock)) {
            throw new RuntimeException('This BusinessOS installation is already locked.');
        }

        $license = $this->licenses->verifySignedToken($input['license_token'], $input['license_domain']);
        if (! $license['valid']) {
            throw new RuntimeException($license['error'] ?? 'The offline license entitlement is invalid.');
        }

        $database = array_intersect_key($input, array_flip(['db_connection', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password']));
        $this->database->test($database);

        if (config('installer.persist_environment') && ! app()->environment('testing')) {
            $this->environment->write($this->database->environment($database) + [
                'APP_NAME' => $input['site_name'],
                'APP_URL' => $input['app_url'],
                'APP_ENV' => $input['app_environment'],
                'APP_DEBUG' => 'false',
            ]);
            Artisan::call('config:clear');
        }

        $this->database->connect($database);
        Artisan::call('migrate', ['--force' => true, '--database' => 'installer']);
        Artisan::call('db:seed', ['--force' => true, '--database' => 'installer']);

        return DB::connection('installer')->transaction(function () use ($input, $license, $lock) {
            $admin = User::on('installer')->updateOrCreate(
                ['email' => $input['admin_email']],
                ['name' => $input['admin_name'], 'password' => Hash::make($input['admin_password']), 'role' => 'super_admin', 'is_active' => true, 'email_verified_at' => now()],
            );

            foreach ([
                'site_name' => $input['site_name'],
                'app_url' => $input['app_url'],
                'default_currency' => $input['currency'],
                'default_timezone' => $input['timezone'],
                'default_language' => $input['language'],
                'storage_driver' => $input['storage_driver'],
                'installed_modules' => json_encode($input['modules']),
                'license_entitlements' => json_encode($license['payload']['entitlements'] ?? []),
                'app_version' => config('app.version', '1.0.0'),
            ] as $key => $value) {
                Setting::on('installer')->updateOrCreate(['key' => $key, 'workspace_id' => null], ['value' => $value, 'created_by' => $admin->id]);
            }

            Language::on('installer')->firstOrCreate(['code' => $input['language']], ['name' => strtoupper($input['language']), 'status' => true]);
            Plan::on('installer')->firstOrCreate(['name' => 'Free'], [
                'package_price_monthly' => 0,
                'package_price_yearly' => 0,
                'number_of_users' => 3,
                'storage_limit' => 1024 * 1024 * 100,
                'workspace_limit' => 1,
                'modules' => $input['modules'],
                'free_plan' => true,
                'status' => true,
                'created_by' => $admin->id,
            ]);

            $payload = json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('app.version', '1.0.0')], JSON_THROW_ON_ERROR);
            if (file_put_contents($lock, $payload, LOCK_EX) === false) {
                throw new RuntimeException('Unable to create the installation lock.');
            }

            return $admin;
        });
    }
}
