<?php

namespace App\Services\Updates;

use App\Services\Backups\BackupService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

final class UpdateService
{
    public function __construct(
        private readonly ReleaseChecker $releaseChecker,
        private readonly BackupService $backupService,
    ) {}

    public function check(): ReleaseCheckResult
    {
        return $this->releaseChecker->check();
    }

    public function prepare(): UpdatePreparation
    {
        $check = $this->releaseChecker->check();

        return new UpdatePreparation($check->updateAvailable(), $check);
    }

    /**
     * @param  array<string, mixed>|null  $storedCheck
     * @return array<string, mixed>
     */
    public function install(?array $storedCheck = null): array
    {
        $check = ReleaseCheckResult::fromArray($storedCheck);

        if (! $check?->updateAvailable() || ! $check->release instanceof ReleaseInfo) {
            throw new RuntimeException('No checked update is available for installation.');
        }

        $release = $check->release;

        if ($release->zipUrl === '' || $release->sha256 === '') {
            throw new RuntimeException('The update manifest is missing the archive URL or checksum.');
        }

        $this->logStatus('started', [
            'version' => $release->tagName,
        ]);

        $backup = $this->backupService->create('pre-update-'.$release->version);
        $workDirectory = $this->updatesDirectory().'/'.now()->format('Ymd-His').'-'.preg_replace('/[^A-Za-z0-9_.-]/', '-', $release->version);
        $downloadPath = $workDirectory.'/release.zip';
        $extractDirectory = $workDirectory.'/extract';
        $rollbackDirectory = $workDirectory.'/rollback';

        File::ensureDirectoryExists($workDirectory);

        try {
            $this->downloadArchive($release, $downloadPath);
            $this->assertChecksum($downloadPath, $release->sha256);
            $this->extractArchive($downloadPath, $extractDirectory);

            $packageRoot = $this->packageRoot($extractDirectory);
            $this->validatePackageRoot($packageRoot);
            $this->assertPackageDoesNotShipSecrets($packageRoot);

            $this->enterMaintenanceMode();
            $this->snapshotCurrentApplication($rollbackDirectory);

            try {
                $this->replaceApplicationFiles($packageRoot);

                if (app()->environment('testing') && (bool) config('shelfvault.updates.fail_after_replace', false)) {
                    throw new RuntimeException('Simulated update failure after file replacement.');
                }

                $this->runPostUpdateCommands($release);
            } catch (Throwable $exception) {
                $this->restoreRollback($rollbackDirectory);

                throw $exception;
            } finally {
                $this->leaveMaintenanceMode();
            }

            $result = [
                'version' => $release->tagName,
                'backup_path' => $backup['path'],
                'installed_at' => now()->toIso8601String(),
            ];

            $this->logStatus('installed', $result);

            return $result;
        } catch (Throwable $exception) {
            $this->logStatus('failed', [
                'version' => $release->tagName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'backup_path' => $backup['path'] ?? null,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>|null  $storedCheck
     * @return array<string, mixed>
     */
    public function summary(?array $storedCheck = null): array
    {
        $currentVersion = $this->currentVersion();
        $check = ReleaseCheckResult::fromArray($storedCheck);

        if ($check?->currentVersion !== $currentVersion) {
            $check = null;
        }

        $release = $check?->release;
        $installationMode = $this->installationMode();

        return [
            'current_version' => $currentVersion,
            'status' => $check?->status ?? 'unknown',
            'latest_version' => $release?->tagName,
            'release_name' => $release?->name,
            'release_url' => $release?->htmlUrl,
            'changelog' => $release?->body,
            'checked_at' => $check?->checkedAt,
            'installation_mode' => $installationMode,
            'strategy_label' => __('admin.settings.updates.installation_modes.'.$installationMode),
            'backup_required' => (bool) config('shelfvault.updates.backup_required', true),
            'auto_update_enabled' => false,
            'can_prepare' => $check?->updateAvailable() ?? false,
            'can_install' => ($check?->updateAvailable() ?? false)
                && ($release?->zipUrl ?? '') !== ''
                && ($release?->sha256 ?? '') !== '',
            'zip_url' => $release?->zipUrl,
            'sha256' => $release?->sha256,
            'minimum_php' => $release?->minimumPhp,
            'requires_migrations' => $release?->requiresMigrations ?? true,
            'manifest_url' => config('shelfvault.updates.manifest_url'),
            'last_update' => $this->lastStatus(),
        ];
    }

    private function currentVersion(): string
    {
        return trim((string) config('shelfvault.version', '0.1.0-dev')) ?: '0.1.0-dev';
    }

    private function installationMode(): string
    {
        $configured = strtolower(trim((string) config('shelfvault.updates.installation_mode', 'auto')));

        if (in_array($configured, ['docker', 'classic'], true)) {
            return $configured;
        }

        return file_exists('/.dockerenv') ? 'docker' : 'classic';
    }

    private function downloadArchive(ReleaseInfo $release, string $downloadPath): void
    {
        if (! $this->isHttpsUrl($release->zipUrl)) {
            throw new RuntimeException('Update archives must be downloaded over HTTPS.');
        }

        $response = Http::timeout(max(5, (int) config('shelfvault.updates.download_timeout', 60)))
            ->get($release->zipUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Update archive download failed with HTTP '.$response->status().'.');
        }

        File::put($downloadPath, $response->body());
    }

    private function assertChecksum(string $path, string $expected): void
    {
        $actual = hash_file('sha256', $path);

        if (! is_string($actual) || ! hash_equals(strtolower($expected), strtolower($actual))) {
            throw new RuntimeException('Update archive checksum verification failed.');
        }
    }

    private function extractArchive(string $archivePath, string $extractDirectory): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to install updates.');
        }

        File::ensureDirectoryExists($extractDirectory);

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Update archive could not be opened.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));

            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../') || str_contains($name, '..\\')) {
                $zip->close();

                throw new RuntimeException('Update archive contains an unsafe path.');
            }
        }

        if (! $zip->extractTo($extractDirectory)) {
            $zip->close();

            throw new RuntimeException('Update archive could not be extracted.');
        }

        $zip->close();
    }

    private function packageRoot(string $extractDirectory): string
    {
        if (is_file($extractDirectory.'/artisan')) {
            return $extractDirectory;
        }

        $children = collect(File::directories($extractDirectory));

        if ($children->count() === 1 && is_file($children->first().'/artisan')) {
            return (string) $children->first();
        }

        return $extractDirectory;
    }

    private function validatePackageRoot(string $packageRoot): void
    {
        foreach (['artisan', 'app', 'bootstrap', 'config', 'public/index.php', 'vendor/autoload.php'] as $required) {
            if (! file_exists($packageRoot.'/'.$required)) {
                throw new RuntimeException('Update archive is not a complete ShelfVault package.');
            }
        }
    }

    private function assertPackageDoesNotShipSecrets(string $packageRoot): void
    {
        foreach (['.env', 'storage/logs/laravel.log', 'bootstrap/cache/config.php', 'public/hot'] as $forbidden) {
            if (file_exists($packageRoot.'/'.$forbidden)) {
                throw new RuntimeException('Update archive contains files that must not be shipped.');
            }
        }
    }

    private function snapshotCurrentApplication(string $rollbackDirectory): void
    {
        File::ensureDirectoryExists($rollbackDirectory);

        $this->copyReplaceableContents($this->appRoot(), $rollbackDirectory);
    }

    private function replaceApplicationFiles(string $packageRoot): void
    {
        $this->deleteReplaceableContents($this->appRoot());
        $this->copyReplaceableContents($packageRoot, $this->appRoot());
    }

    private function restoreRollback(string $rollbackDirectory): void
    {
        if (! is_dir($rollbackDirectory)) {
            return;
        }

        $this->deleteReplaceableContents($this->appRoot());
        $this->copyReplaceableContents($rollbackDirectory, $this->appRoot(), preserve: false);
    }

    private function runPostUpdateCommands(ReleaseInfo $release): void
    {
        if ((bool) config('shelfvault.updates.skip_artisan', false)) {
            return;
        }

        $kernel = app(ConsoleKernel::class);

        if ($release->requiresMigrations) {
            $this->callArtisan($kernel, 'migrate', ['--force' => true, '--no-interaction' => true]);
        }

        $this->callArtisan($kernel, 'optimize:clear', ['--no-interaction' => true]);
        $this->callArtisan($kernel, 'storage:link', ['--no-interaction' => true], true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function callArtisan(ConsoleKernel $kernel, string $command, array $parameters = [], bool $allowFailure = false): void
    {
        $exitCode = $kernel->call($command, $parameters);

        if ($exitCode !== 0 && ! $allowFailure) {
            throw new RuntimeException('Artisan command failed: '.$command);
        }
    }

    private function enterMaintenanceMode(): void
    {
        if ((bool) config('shelfvault.updates.skip_artisan', false)) {
            return;
        }

        app(ConsoleKernel::class)->call('down', ['--render' => 'errors::503', '--no-interaction' => true]);
    }

    private function leaveMaintenanceMode(): void
    {
        if ((bool) config('shelfvault.updates.skip_artisan', false)) {
            return;
        }

        app(ConsoleKernel::class)->call('up', ['--no-interaction' => true]);
    }

    private function deleteReplaceableContents(string $root, string $relative = ''): void
    {
        foreach (File::directories($root) as $directory) {
            $childRelative = ltrim($relative.'/'.basename($directory), '/');

            if ($this->isPreservedPath($childRelative)) {
                continue;
            }

            if ($this->hasPreservedChildren($childRelative)) {
                $this->deleteReplaceableContents($directory, $childRelative);

                continue;
            }

            File::deleteDirectory($directory);
        }

        foreach (File::files($root) as $file) {
            $childRelative = ltrim($relative.'/'.$file->getFilename(), '/');

            if (! $this->isPreservedPath($childRelative)) {
                File::delete($file->getPathname());
            }
        }
    }

    private function copyReplaceableContents(string $sourceRoot, string $targetRoot, string $relative = '', bool $preserve = true): void
    {
        File::ensureDirectoryExists($targetRoot);

        foreach (File::directories($sourceRoot) as $directory) {
            $childRelative = ltrim($relative.'/'.basename($directory), '/');

            if ($preserve && $this->isPreservedPath($childRelative)) {
                continue;
            }

            $this->copyReplaceableContents($directory, $targetRoot.'/'.basename($directory), $childRelative, $preserve);
        }

        foreach (File::files($sourceRoot) as $file) {
            $childRelative = ltrim($relative.'/'.$file->getFilename(), '/');

            if ($preserve && $this->isPreservedPath($childRelative)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetRoot.'/'.$file->getFilename()));
            File::copy($file->getPathname(), $targetRoot.'/'.$file->getFilename());
        }
    }

    private function isPreservedPath(string $relative): bool
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        return $relative === '.env'
            || $relative === 'storage'
            || str_starts_with($relative, 'storage/')
            || $relative === 'public/storage'
            || str_starts_with($relative, 'public/storage/');
    }

    private function hasPreservedChildren(string $relative): bool
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        return $relative === 'public';
    }

    private function appRoot(): string
    {
        return rtrim((string) config('shelfvault.updates.app_root', base_path()), '/');
    }

    private function updatesDirectory(): string
    {
        return $this->appRoot().'/storage/app/shelfvault/updates';
    }

    private function isHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return (parse_url($url, PHP_URL_SCHEME) === 'https');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logStatus(string $status, array $payload): void
    {
        $record = [
            'status' => $status,
            'at' => now()->toIso8601String(),
            ...$payload,
        ];

        File::ensureDirectoryExists($this->updatesDirectory());
        File::put($this->updatesDirectory().'/last-status.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Log::info('ShelfVault update '.$status.'.', array_filter($record, fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastStatus(): ?array
    {
        $path = $this->updatesDirectory().'/last-status.json';

        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) File::get($path), true);

        return is_array($payload) ? $payload : null;
    }
}
