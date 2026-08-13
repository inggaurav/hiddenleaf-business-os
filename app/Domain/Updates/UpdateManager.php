<?php

namespace App\Domain\Updates;

use App\Models\Setting;
use App\Models\UpdateHistory;
use App\Models\User;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateManager
{
    public function __construct(
        private readonly UpdateManifestService $manifests,
        private readonly SafeUpdateArchive $archives,
        private readonly UpdateBackupManager $backups,
        private readonly AuditLogger $audit,
    ) {}

    public function check(?string $channel = null): array
    {
        $manifest = $this->manifests->fetch($channel);
        $this->assertCompatible($manifest);
        $current = admin_setting('app_version', '1.0.0');

        return $manifest + ['current_version' => $current, 'update_available' => version_compare($manifest['version'], $current, '>')];
    }

    public function install(array $manifest, User $user): UpdateHistory
    {
        $manifest = $this->manifests->verify($manifest, $manifest['channel'] ?? null);
        $this->assertCompatible($manifest);
        $current = admin_setting('app_version', '1.0.0');
        if (! version_compare($manifest['version'], $current, '>')) {
            throw new RuntimeException('The update version must be newer than the installed version.');
        }

        $history = UpdateHistory::create([
            'from_version' => $current,
            'to_version' => $manifest['version'],
            'channel' => $manifest['channel'],
            'status' => 'downloading',
            'manifest' => $manifest,
            'started_by' => $user->id,
            'started_at' => now(),
        ]);
        $staging = rtrim(config('updater.staging_directory'), '/\\').DIRECTORY_SEPARATOR.$history->id.'-'.Str::random(12);
        $archive = $staging.'/update.zip';
        $backup = rtrim(config('updater.backup_directory'), '/\\').DIRECTORY_SEPARATOR.$history->id;
        $maintenance = false;

        try {
            $this->audit->log($user->id, null, null, 'update.install.started', 'update_history', (string) $history->id, [
                'from_version' => $current,
                'to_version' => $manifest['version'],
                'channel' => $manifest['channel'],
            ], critical: true);
            File::ensureDirectoryExists($staging);
            $response = Http::timeout(config('updater.download_timeout'))->get($manifest['package_url'])->throw();
            File::put($archive, $response->body());
            if (! hash_equals(strtolower($manifest['sha256']), hash_file('sha256', $archive))) {
                throw new RuntimeException('The update package checksum does not match its signed manifest.');
            }

            $payload = $this->archives->extract($archive, $staging.'/extracted');
            $files = iterator_to_array($this->archives->files($payload));
            if ($files === []) {
                throw new RuntimeException('The update payload is empty.');
            }

            $history->update(['status' => 'backing_up', 'backup_path' => $backup]);
            $this->backups->create($files, config('updater.application_root'), $backup);
            Artisan::call('down');
            $maintenance = true;
            $history->update(['status' => 'installing']);
            $this->deploy($files);
            Artisan::call('migrate', ['--force' => true]);
            $this->refreshCaches();
            Setting::updateOrCreate(
                ['key' => 'app_version', 'workspace_id' => null],
                ['value' => $manifest['version'], 'created_by' => $user->id],
            );
            $history->update(['status' => 'completed', 'completed_at' => now()]);
            $this->audit->log($user->id, null, null, 'update.install.completed', 'update_history', (string) $history->id, [
                'from_version' => $current,
                'to_version' => $manifest['version'],
            ], critical: true);

            return $history->refresh();
        } catch (Throwable $exception) {
            if ($history->backup_path && is_file($history->backup_path.'/backup.json')) {
                $this->backups->restore(config('updater.application_root'), $history->backup_path);
            }
            $history->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 2000)]);
            throw $exception;
        } finally {
            if ($maintenance) {
                Artisan::call('up');
            }
            File::deleteDirectory($staging);
        }
    }

    public function rollback(UpdateHistory $history, User $user): UpdateHistory
    {
        if ($history->status !== 'completed' || ! $history->backup_path) {
            throw new RuntimeException('Only a completed update with a backup can be rolled back.');
        }

        $this->audit->log($user->id, null, null, 'update.rollback.started', 'update_history', (string) $history->id, [
            'from_version' => $history->to_version,
            'to_version' => $history->from_version,
        ], critical: true);
        Artisan::call('down');
        try {
            $this->backups->restore(config('updater.application_root'), $history->backup_path);
            Setting::updateOrCreate(
                ['key' => 'app_version', 'workspace_id' => null],
                ['value' => $history->from_version, 'created_by' => $user->id],
            );
            $this->refreshCaches();
            $history->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);
            $this->audit->log($user->id, null, null, 'update.rollback.completed', 'update_history', (string) $history->id, [
                'restored_version' => $history->from_version,
            ], critical: true);

            return $history->refresh();
        } finally {
            Artisan::call('up');
        }
    }

    private function deploy(array $files): void
    {
        $root = rtrim(config('updater.application_root'), '/\\');
        foreach ($files as $source => $relative) {
            $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            File::ensureDirectoryExists(dirname($target));
            $temporary = $target.'.update-'.Str::random(8);
            File::copy($source, $temporary);
            if (! @rename($temporary, $target)) {
                File::delete($temporary);
                throw new RuntimeException("Unable to install update file {$relative}.");
            }
        }
    }

    private function assertCompatible(array $manifest): void
    {
        if (version_compare(PHP_VERSION, $manifest['minimum_php'], '<')) {
            throw new RuntimeException("This update requires PHP {$manifest['minimum_php']} or newer.");
        }
        $laravel = app()->version();
        if (version_compare($laravel, $manifest['minimum_laravel'], '<')) {
            throw new RuntimeException("This update requires Laravel {$manifest['minimum_laravel']} or newer.");
        }
    }

    private function refreshCaches(): void
    {
        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            Artisan::call($command);
        }
    }
}
