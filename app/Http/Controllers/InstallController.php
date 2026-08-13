<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class InstallController extends Controller
{
    public function index()
    {
        $requirements = [
            'php' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'extensions' => [
                'bcmath' => extension_loaded('bcmath'),
                'ctype' => extension_loaded('ctype'),
                'fileinfo' => extension_loaded('fileinfo'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'openssl' => extension_loaded('openssl'),
                'pdo' => extension_loaded('pdo'),
                'tokenizer' => extension_loaded('tokenizer'),
                'xml' => extension_loaded('xml'),
            ],
            'permissions' => [
                'storage' => is_writable(storage_path()),
                'bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
            ],
        ];

        return Inertia::render('Install/Index', [
            'requirements' => $requirements,
            'isInstalled' => File::exists(storage_path('installed')),
        ]);
    }

    public function testDatabase(Request $request)
    {
        $validated = $request->validate([
            'db_connection' => 'nullable|string',
            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            $driver = $validated['db_connection'] ?? 'mysql';
            $dsn = "{$driver}:host={$validated['db_host']};port={$validated['db_port']};dbname={$validated['db_database']}";
            $pdo = new \PDO($dsn, $validated['db_username'], $validated['db_password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            return response()->json(['success' => true, 'message' => 'Database connection successful.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function setup(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        // Run migrations
        Artisan::call('migrate', ['--force' => true]);

        // Create or update Super Admin user
        $admin = User::updateOrCreate(
            ['email' => $validated['admin_email']],
            [
                'name' => $validated['admin_name'],
                'password' => Hash::make($validated['admin_password']),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Store site settings
        Setting::updateOrCreate(
            ['key' => 'site_name', 'workspace_id' => null],
            ['value' => $validated['site_name'], 'created_by' => $admin->id]
        );

        // Write installed lock file
        File::put(storage_path('installed'), 'Installed successfully on '.now()->toIso8601String());

        return redirect()->route('login')->with('success', 'Installation completed successfully. Please sign in with your administrator account.');
    }
}
