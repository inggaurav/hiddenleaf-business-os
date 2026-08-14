<?php

namespace App\Http\Controllers;

use App\Domain\Installation\DatabaseConfigurator;
use App\Domain\Installation\InstallationService;
use App\Services\ModuleManager;
use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InstallController extends Controller
{
    public function index(ModuleManager $modules): Response|RedirectResponse
    {
        if ($this->installed()) {
            return redirect()->route('login');
        }

        $requirements = [
            'php' => version_compare(PHP_VERSION, config('installer.minimum_php'), '>='),
            'php_version' => PHP_VERSION,
            'minimum_php' => config('installer.minimum_php'),
            'extensions' => collect(['bcmath', 'ctype', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'])->mapWithKeys(fn ($extension) => [$extension => extension_loaded($extension)]),
            'permissions' => [
                'storage' => is_writable(storage_path()),
                'bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
                'environment' => is_writable(base_path('.env')) || (! is_file(base_path('.env')) && is_writable(base_path())),
            ],
        ];

        return Inertia::render('Install/Index', [
            'steps' => ['Welcome', 'Requirements', 'Permissions', 'Environment', 'Database', 'License', 'Super Admin', 'Modules', 'Finalize'],
            'requirements' => $requirements,
            'isInstalled' => false,
            'modules' => collect($modules->getAllModules())->map(fn ($module) => ['alias' => $module->getAlias(), 'name' => $module->getName()])->values(),
            'businessOsVersion' => config('modules.core_version'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }

    public function validateLicense(Request $request, LicenseManager $licenses): JsonResponse
    {
        $this->abortWhenInstalled();
        $validated = $request->validate([
            'license_token' => ['required', 'string', 'max:65536'],
            'license_domain' => ['required', 'string', 'max:253', 'regex:/^(localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}|\d{1,3}(?:\.\d{1,3}){3})$/i'],
        ]);

        $result = $licenses->verifySignedToken($validated['license_token'], $validated['license_domain']);
        $status = (string) ($result['status'] ?? ($result['valid'] ? 'valid' : 'invalid'));

        if ($result['valid']) {
            return response()->json([
                'valid' => true,
                'status' => 'valid',
                'message' => ! empty($result['grace_period'])
                    ? 'License validated within its configured grace period.'
                    : 'License validated.',
            ]);
        }

        $message = match ($status) {
            'expired' => 'The license has expired.',
            'revoked' => 'The license has been revoked.',
            'suspended' => 'The license has been suspended.',
            'domain_mismatch' => 'The license is not valid for this domain.',
            'not_active' => 'The license is not active.',
            'configuration_error' => 'License validation is unavailable.',
            'invalid_signature' => 'The license signature is invalid.',
            default => 'The license token is invalid.',
        };

        return response()->json(['valid' => false, 'status' => $status, 'message' => $message], 422);
    }

    public function testDatabase(Request $request, DatabaseConfigurator $database): JsonResponse
    {
        $this->abortWhenInstalled();
        $validated = $request->validate($this->databaseRules());

        try {
            $database->test($validated);

            return response()->json(['success' => true, 'message' => 'Database connection successful.']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Database connection failed. Verify the driver, host, credentials, database, and TLS/network configuration.'], 422);
        }
    }

    public function setup(Request $request, InstallationService $installer, ModuleManager $modules): RedirectResponse
    {
        $this->abortWhenInstalled();
        $availableModules = array_keys($modules->getAllModules());
        $validated = $request->validate($this->databaseRules() + [
            'site_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:2048'],
            'app_environment' => ['required', Rule::in(['production', 'staging', 'local'])],
            'license_token' => ['required', 'string'],
            'license_domain' => ['required', 'string', 'max:253'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:12', 'confirmed'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in($availableModules)],
            'language' => ['required', 'string', 'max:10'],
            'currency' => ['required', 'string', 'size:3'],
            // Browsers may return backwards-compatible IANA aliases such as
            // Asia/Calcutta. PHP supports them, so the installer must accept
            // the same identifiers it can legitimately prefill.
            'timezone' => ['required', 'timezone:all_with_bc'],
            'storage_driver' => ['required', Rule::in(['local', 's3'])],
        ]);

        try {
            $installer->install($validated);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->except(['admin_password', 'admin_password_confirmation', 'db_password', 'license_token']))
                ->withErrors(['installation' => $exception->getMessage()]);
        }

        return redirect()->route('login')->with('success', 'Installation completed successfully. Sign in with the administrator account.');
    }

    private function databaseRules(): array
    {
        $drivers = app()->environment('testing') ? ['pgsql', 'mysql', 'sqlite'] : ['pgsql', 'mysql'];

        return [
            'db_connection' => ['required', Rule::in($drivers)],
            'db_host' => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:253'],
            'db_port' => ['required_unless:db_connection,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:1024'],
            'db_username' => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:2048'],
        ];
    }

    private function abortWhenInstalled(): void
    {
        abort_if($this->installed(), 404);
    }

    private function installed(): bool
    {
        return is_file(config('installer.lock_file'));
    }
}
