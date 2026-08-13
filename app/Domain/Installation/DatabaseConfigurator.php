<?php

namespace App\Domain\Installation;

use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseConfigurator
{
    public function test(array $database): void
    {
        $pdo = new PDO($this->dsn($database), $database['db_username'] ?? null, $database['db_password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
    }

    public function connect(array $database): void
    {
        $driver = $database['db_connection'];
        $configuration = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => $database['db_database'], 'prefix' => '', 'foreign_key_constraints' => true]
            : [
                'driver' => $driver,
                'host' => $database['db_host'],
                'port' => $database['db_port'],
                'database' => $database['db_database'],
                'username' => $database['db_username'],
                'password' => $database['db_password'] ?? '',
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
            ];

        if ($driver === 'pgsql') {
            $configuration += ['search_path' => 'public', 'sslmode' => 'prefer'];
        } elseif ($driver === 'mysql') {
            $configuration += ['collation' => 'utf8mb4_unicode_ci', 'strict' => true, 'engine' => null];
            $configuration['charset'] = 'utf8mb4';
        }

        config(['database.connections.installer' => $configuration, 'database.default' => 'installer']);
        DB::purge('installer');
        DB::connection('installer')->getPdo();
    }

    public function environment(array $database): array
    {
        return [
            'DB_CONNECTION' => $database['db_connection'],
            'DB_HOST' => $database['db_host'] ?? '',
            'DB_PORT' => $database['db_port'] ?? '',
            'DB_DATABASE' => $database['db_database'],
            'DB_USERNAME' => $database['db_username'] ?? '',
            'DB_PASSWORD' => $database['db_password'] ?? '',
        ];
    }

    private function dsn(array $database): string
    {
        return match ($database['db_connection']) {
            'pgsql' => "pgsql:host={$database['db_host']};port={$database['db_port']};dbname={$database['db_database']}",
            'mysql' => "mysql:host={$database['db_host']};port={$database['db_port']};dbname={$database['db_database']};charset=utf8mb4",
            'sqlite' => 'sqlite:'.$database['db_database'],
        };
    }
}
