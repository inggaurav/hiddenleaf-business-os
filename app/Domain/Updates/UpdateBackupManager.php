<?php

namespace App\Domain\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class UpdateBackupManager
{
    public function create(iterable $files, string $root, string $backup): array
    {
        File::ensureDirectoryExists($backup.'/files');
        $metadata = ['existing_files' => [], 'new_files' => [], 'database' => $this->backupDatabase($backup)];

        foreach ($files as $source => $relative) {
            $target = $this->target($root, $relative);
            if (is_file($target)) {
                $destination = $backup.'/files/'.$relative;
                File::ensureDirectoryExists(dirname($destination));
                File::copy($target, $destination);
                $metadata['existing_files'][] = $relative;
            } else {
                $metadata['new_files'][] = $relative;
            }
        }

        File::put($backup.'/backup.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $metadata;
    }

    public function restore(string $root, string $backup): void
    {
        $metadataFile = $backup.'/backup.json';
        if (! is_file($metadataFile)) {
            throw new RuntimeException('The update backup metadata is missing.');
        }

        $metadata = json_decode(File::get($metadataFile), true, flags: JSON_THROW_ON_ERROR);
        foreach ($metadata['existing_files'] ?? [] as $relative) {
            $source = $backup.'/files/'.$relative;
            $target = $this->target($root, $relative);
            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);
        }
        foreach ($metadata['new_files'] ?? [] as $relative) {
            $target = $this->target($root, $relative);
            if (is_file($target)) {
                File::delete($target);
            }
        }

        $this->restoreDatabase($metadata['database'] ?? null);
    }

    private function backupDatabase(string $backup): ?array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection === 'sqlite') {
            if (! is_string($database) || $database === ':memory:' || ! is_file($database)) {
                return null;
            }
            $destination = $backup.'/database.sqlite';
            File::copy($database, $destination);

            return ['driver' => 'sqlite', 'source' => $destination, 'target' => $database];
        }

        $dump = $backup.'/database.sql';
        $config = config("database.connections.{$connection}", []);
        $process = match ($connection) {
            'pgsql' => new Process(['pg_dump', '--clean', '--if-exists', '--no-owner', '--no-acl', '--host='.$config['host'], '--port='.(string) $config['port'], '--username='.$config['username'], '--file='.$dump, $database], null, ['PGPASSWORD' => (string) $config['password']]),
            'mysql' => new Process(['mysqldump', '--add-drop-table', '--host='.$config['host'], '--port='.(string) $config['port'], '--user='.$config['username'], '--result-file='.$dump, $database], null, ['MYSQL_PWD' => (string) $config['password']]),
            default => throw new RuntimeException("Database backup is unsupported for connection {$connection}."),
        };
        $process->setTimeout(300)->mustRun();

        return ['driver' => $connection, 'source' => $dump, 'database' => $database, 'config' => $config];
    }

    private function restoreDatabase(?array $database): void
    {
        if (! $database) {
            return;
        }
        if ($database['driver'] === 'sqlite') {
            File::copy($database['source'], $database['target']);

            return;
        }

        $config = $database['config'];
        $process = match ($database['driver']) {
            'pgsql' => new Process(['psql', '--host='.$config['host'], '--port='.(string) $config['port'], '--username='.$config['username'], '--dbname='.$database['database'], '--file='.$database['source']], null, ['PGPASSWORD' => (string) $config['password']]),
            'mysql' => new Process(['mysql', '--host='.$config['host'], '--port='.(string) $config['port'], '--user='.$config['username'], $database['database']], null, ['MYSQL_PWD' => (string) $config['password']], file_get_contents($database['source'])),
            default => throw new RuntimeException('The database backup driver is unsupported.'),
        };
        $process->setTimeout(300)->mustRun();
    }

    private function target(string $root, string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative) || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('An update file path is unsafe.');
        }

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
