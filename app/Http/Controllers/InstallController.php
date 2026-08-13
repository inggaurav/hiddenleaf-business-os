<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallController
{
    public function index()
    {
        return Inertia::render('Install/Index', [
            'requirements' => [
                'php' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'extensions' => [
                    'bcmath' => extension_loaded('bcmath'),
                    'ctype' => extension_loaded('ctype'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring'),
                    'openssl' => extension_loaded('openssl'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'tokenizer' => extension_loaded('tokenizer'),
                    'xml' => extension_loaded('xml'),
                ]
            ]
        ]);
    }

    public function setup(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
        ]);

        if ($request->license_key !== 'DUMMY-LICENSE-KEY') {
            return back()->withErrors(['license_key' => 'Invalid license key. Use DUMMY-LICENSE-KEY.']);
        }

        // Just creating the installed file
        File::put(storage_path('installed'), 'Installed at ' . now());

        return redirect('/login');
    }
}
