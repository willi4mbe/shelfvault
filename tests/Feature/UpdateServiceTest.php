<?php

namespace Tests\Feature;

use App\Services\Updates\UpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class UpdateServiceTest extends TestCase
{
    private string $databasePath;

    private string $appRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/shelfvault-updates.sqlite');
        $this->appRoot = storage_path('framework/testing/update-app');

        File::delete($this->databasePath);
        File::deleteDirectory($this->appRoot);
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::put($this->databasePath, '');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'shelfvault.version' => '1.0.0',
            'shelfvault.updates.app_root' => $this->appRoot,
            'shelfvault.updates.manifest_url' => 'https://updates.example.test/manifest.json',
            'shelfvault.updates.skip_artisan' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->writeInstalledAppRoot();
    }

    protected function tearDown(): void
    {
        File::delete($this->databasePath);
        File::deleteDirectory($this->appRoot);

        parent::tearDown();
    }

    public function test_manifest_check_detects_newer_semver_release(): void
    {
        $zip = $this->buildPackageZip(['VERSION' => '1.1.0']);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->manifest('1.1.0', hash('sha256', $zip)), 200),
        ]);

        $check = app(UpdateService::class)->check();

        $this->assertTrue($check->updateAvailable());
        $this->assertSame('1.1.0', $check->release?->version);
        $this->assertSame(hash('sha256', $zip), $check->release?->sha256);
    }

    public function test_manifest_validation_rejects_missing_checksum(): void
    {
        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response([
                'version' => '1.1.0',
                'zip_url' => 'https://updates.example.test/ShelfVault-1.1.0.zip',
            ], 200),
        ]);

        $this->assertFalse(app(UpdateService::class)->check()->updateAvailable());
    }

    public function test_update_install_rejects_checksum_mismatch(): void
    {
        $zip = $this->buildPackageZip(['VERSION' => '1.1.0']);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->manifest('1.1.0', str_repeat('a', 64)), 200),
            'https://updates.example.test/ShelfVault-1.1.0.zip' => Http::response($zip, 200),
        ]);

        $service = app(UpdateService::class);
        $check = $service->check();

        $this->expectExceptionMessage('checksum');

        $service->install($check->toArray());
    }

    public function test_update_install_rejects_zip_traversal(): void
    {
        $zip = $this->buildPackageZip(['../evil.txt' => 'bad']);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->manifest('1.1.0', hash('sha256', $zip)), 200),
            'https://updates.example.test/ShelfVault-1.1.0.zip' => Http::response($zip, 200),
        ]);

        $service = app(UpdateService::class);

        $this->expectExceptionMessage('unsafe path');

        $service->install($service->check()->toArray());
    }

    public function test_update_install_preserves_env_storage_and_public_storage(): void
    {
        $zip = $this->buildPackageZip([
            'app/NewFile.php' => '<?php echo "new";',
            'VERSION' => '1.1.0',
        ]);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->manifest('1.1.0', hash('sha256', $zip)), 200),
            'https://updates.example.test/ShelfVault-1.1.0.zip' => Http::response($zip, 200),
        ]);

        $service = app(UpdateService::class);
        $result = $service->install($service->check()->toArray());

        $this->assertSame('v1.1.0', $result['version']);
        $this->assertSame('APP_KEY=base64:old-key', trim(File::get($this->appRoot.'/.env')));
        $this->assertSame('cover', File::get($this->appRoot.'/storage/app/public/covers/cover.txt'));
        $this->assertSame('linked', File::get($this->appRoot.'/public/storage/linked.txt'));
        $this->assertFileExists($this->appRoot.'/app/NewFile.php');
        $this->assertFileDoesNotExist($this->appRoot.'/app/OldFile.php');
        $this->assertFileExists($result['backup_path']);
    }

    public function test_update_install_restores_files_when_replacement_fails(): void
    {
        config(['shelfvault.updates.fail_after_replace' => true]);

        $zip = $this->buildPackageZip([
            'app/NewFile.php' => '<?php echo "new";',
            'VERSION' => '1.1.0',
        ]);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->manifest('1.1.0', hash('sha256', $zip)), 200),
            'https://updates.example.test/ShelfVault-1.1.0.zip' => Http::response($zip, 200),
        ]);

        $service = app(UpdateService::class);

        try {
            $service->install($service->check()->toArray());
            $this->fail('The simulated update failure was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Simulated update failure', $exception->getMessage());
        }

        $this->assertFileExists($this->appRoot.'/app/OldFile.php');
        $this->assertFileDoesNotExist($this->appRoot.'/app/NewFile.php');
        $this->assertSame('APP_KEY=base64:old-key', trim(File::get($this->appRoot.'/.env')));
        $this->assertSame('cover', File::get($this->appRoot.'/storage/app/public/covers/cover.txt'));
    }

    private function writeInstalledAppRoot(): void
    {
        foreach ([
            'app',
            'bootstrap/cache',
            'config',
            'public/storage',
            'storage/app/public/covers',
            'storage/app/shelfvault',
            'vendor',
        ] as $directory) {
            File::ensureDirectoryExists($this->appRoot.'/'.$directory);
        }

        File::put($this->appRoot.'/.env', 'APP_KEY=base64:old-key');
        File::put($this->appRoot.'/artisan', '<?php echo "old";');
        File::put($this->appRoot.'/app/OldFile.php', '<?php echo "old";');
        File::put($this->appRoot.'/bootstrap/app.php', '<?php echo "old";');
        File::put($this->appRoot.'/config/app.php', '<?php return [];');
        File::put($this->appRoot.'/public/index.php', '<?php echo "old";');
        File::put($this->appRoot.'/public/storage/linked.txt', 'linked');
        File::put($this->appRoot.'/storage/app/public/covers/cover.txt', 'cover');
        File::put($this->appRoot.'/storage/app/shelfvault/installed.lock', now()->toIso8601String());
        File::put($this->appRoot.'/vendor/autoload.php', '<?php');
    }

    /**
     * @param  array<string, string>  $extraFiles
     */
    private function buildPackageZip(array $extraFiles = []): string
    {
        $path = storage_path('framework/testing/update-package-'.bin2hex(random_bytes(4)).'.zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ([
            'artisan' => '<?php echo "new";',
            'app/.gitignore' => '*',
            'bootstrap/app.php' => '<?php echo "new";',
            'bootstrap/cache/.gitignore' => '*',
            'config/app.php' => '<?php return [];',
            'public/index.php' => '<?php echo "new";',
            'vendor/autoload.php' => '<?php',
        ] + $extraFiles as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        $contents = File::get($path);
        File::delete($path);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $version, string $sha256): array
    {
        return [
            'version' => $version,
            'tag_name' => 'v'.$version,
            'name' => 'ShelfVault '.$version,
            'html_url' => 'https://updates.example.test/releases/'.$version,
            'zip_url' => 'https://updates.example.test/ShelfVault-'.$version.'.zip',
            'sha256' => $sha256,
            'notes' => 'Test update',
            'minimum_php' => '8.3.0',
            'requires_migrations' => true,
        ];
    }
}
