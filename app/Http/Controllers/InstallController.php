<?php

namespace App\Http\Controllers;

use App\Domain\Installation\DatabaseConfigurator;
use App\Domain\Installation\InstallationService;
use App\Services\ModuleManager;
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
        ]);
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
            'timezone' => ['required', 'timezone'],
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
