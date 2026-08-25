<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    private string $defaultLockPath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->lockPath = storage_path('framework/testing/shelfvault-installed.lock');

        File::delete($this->defaultLockPath);
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->lockPath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'shelfvault.installer.lock_path' => $this->lockPath,
            'session.driver' => 'array',
        ]);

        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->lockPath);
        File::delete(storage_path('app/public/covers/test-cover.txt'));

        parent::tearDown();
    }

    public function test_application_routes_redirect_to_standalone_installer_before_setup(): void
    {
        $this->get('/')->assertRedirect('/install.php');
        $this->get('/admin')->assertRedirect('/install.php');
    }

    public function test_laravel_install_route_redirects_to_php_installer_when_bootstrapped(): void
    {
        $this->get('/install')->assertRedirect('/install.php');
    }

    public function test_local_install_preview_renders_without_touching_the_current_installation(): void
    {
        config(['app.env' => 'local']);
        File::put($this->lockPath, now()->toIso8601String());

        $this->get('/dev/install-preview')
            ->assertOk()
            ->assertSee(__('install.requirements.title'))
            ->assertSee(__('install.requirements.ready_short'));

        $this->assertFileExists($this->lockPath);
    }

    public function test_install_preview_is_not_available_outside_local_environments(): void
    {
        config(['app.env' => 'production']);
        File::put($this->lockPath, now()->toIso8601String());

        $this->get('/dev/install-preview')->assertNotFound();
    }

    public function test_standalone_install_page_boots_without_env_file_or_app_key(): void
    {
        $fixtureRoot = $this->freshInstallFixtureRoot();
        $port = $this->reserveLocalPort();
        $process = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.$port,
            '-t',
            $fixtureRoot.'/public',
        ], $fixtureRoot, [
            'APP_ENV' => 'production',
            'APP_KEY' => '',
            'APP_DEBUG' => 'true',
        ]);

        $process->setTimeout(15);
        $process->start();

        try {
            [$status, $body] = $this->waitForHttpResponse('http://127.0.0.1:'.$port.'/install.php');

            $this->assertSame(200, $status, $process->getErrorOutput().PHP_EOL.$body);
            $this->assertStringContainsString('ShelfVault setup', $body);
            $this->assertStringContainsString('Choose your language', $body);
            $this->assertStringNotContainsString('MissingAppKeyException', $body);
            $this->assertFileDoesNotExist($fixtureRoot.'/.env');
            $this->assertFileDoesNotExist($fixtureRoot.'/storage/app/shelfvault/installed.lock');
        } finally {
            $process->stop(0);
            $this->removeDirectory($fixtureRoot);
        }
    }

    public function test_front_controller_routes_install_to_standalone_installer_before_laravel_bootstrap(): void
    {
        $fixtureRoot = $this->freshInstallFixtureRoot();
        $port = $this->reserveLocalPort();
        $process = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.$port,
            '-t',
            $fixtureRoot.'/public',
            $fixtureRoot.'/public/index.php',
        ], $fixtureRoot, [
            'APP_ENV' => 'production',
            'APP_KEY' => '',
            'APP_DEBUG' => 'true',
        ]);

        $process->setTimeout(15);
        $process->start();

        try {
            [$status, $body] = $this->waitForHttpResponse('http://127.0.0.1:'.$port.'/install');

            $this->assertSame(200, $status, $process->getErrorOutput().PHP_EOL.$body);
            $this->assertStringContainsString('Standalone installer for shared hosting.', $body);
            $this->assertStringNotContainsString('MissingAppKeyException', $body);
            $this->assertFileDoesNotExist($fixtureRoot.'/.env');
        } finally {
            $process->stop(0);
            $this->removeDirectory($fixtureRoot);
        }
    }

    public function test_standalone_installer_is_blocked_after_lock_exists(): void
    {
        $fixtureRoot = $this->freshInstallFixtureRoot();
        File::ensureDirectoryExists($fixtureRoot.'/storage/app/shelfvault');
        File::put($fixtureRoot.'/storage/app/shelfvault/installed.lock', now()->toIso8601String());

        $port = $this->reserveLocalPort();
        $process = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.$port,
            '-t',
            $fixtureRoot.'/public',
        ], $fixtureRoot);

        $process->setTimeout(15);
        $process->start();

        try {
            [$status, $body] = $this->waitForHttpResponse('http://127.0.0.1:'.$port.'/install.php');

            $this->assertSame(200, $status, $process->getErrorOutput().PHP_EOL.$body);
            $this->assertStringContainsString('ShelfVault is already installed', $body);
            $this->assertStringContainsString('Go to admin login', $body);
        } finally {
            $process->stop(0);
            $this->removeDirectory($fixtureRoot);
        }
    }

    public function test_laravel_bootstraps_after_env_and_app_key_exist(): void
    {
        $fixtureRoot = $this->freshInstallFixtureRoot(includeLaravel: true);
        $appKey = 'base64:'.base64_encode(random_bytes(32));

        File::put($fixtureRoot.'/.env', implode(PHP_EOL, [
            'APP_NAME=ShelfVault',
            'APP_ENV=production',
            'APP_KEY='.$appKey,
            'APP_DEBUG=false',
            'APP_URL=http://localhost',
            'DB_CONNECTION=sqlite',
            'DB_DATABASE=:memory:',
            'SESSION_DRIVER=file',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
        ]).PHP_EOL);

        $process = new Process([
            PHP_BINARY,
            '-r',
            'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); echo config("app.key");',
        ], $fixtureRoot, [
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
        ]);

        $process->setTimeout(15);
        $process->run();

        try {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().PHP_EOL.$process->getOutput());
            $this->assertSame($appKey, $process->getOutput());
        } finally {
            $this->removeDirectory($fixtureRoot);
        }
    }

    public function test_public_storage_fallback_serves_cover_files_when_installed(): void
    {
        File::put($this->lockPath, now()->toIso8601String());
        File::ensureDirectoryExists(storage_path('app/public/covers'));
        File::put(storage_path('app/public/covers/test-cover.txt'), 'cover-ok');

        $response = $this->get('/storage/covers/test-cover.txt')
            ->assertOk();

        $this->assertSame('cover-ok', $response->streamedContent());
    }

    private function freshInstallFixtureRoot(bool $includeLaravel = false): string
    {
        $root = storage_path('framework/testing/fresh-install-'.bin2hex(random_bytes(6)));

        $this->removeDirectory($root);

        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($root.'/bootstrap/cache');
        File::ensureDirectoryExists($root.'/public');

        foreach (['composer.json', 'composer.lock', 'VERSION', '.env.example'] as $file) {
            File::copy(base_path($file), $root.'/'.$file);
        }

        foreach (['index.php', 'install.php'] as $file) {
            File::copy(public_path($file), $root.'/public/'.$file);
        }

        foreach (['build', 'branding'] as $directory) {
            symlink(public_path($directory), $root.'/public/'.$directory);
        }

        foreach (['apple-touch-icon.png', 'favicon.ico', 'favicon.svg', 'robots.txt'] as $file) {
            File::copy(public_path($file), $root.'/public/'.$file);
        }

        foreach ([
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ] as $directory) {
            File::ensureDirectoryExists($root.'/'.$directory);
        }

        if ($includeLaravel) {
            foreach (['app', 'config', 'database', 'lang', 'resources', 'routes', 'vendor'] as $directory) {
                symlink(base_path($directory), $root.'/'.$directory);
            }

            foreach (['app.php', 'providers.php'] as $file) {
                File::copy(base_path('bootstrap/'.$file), $root.'/bootstrap/'.$file);
            }
        }

        return $root;
    }

    private function reserveLocalPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);

        fclose($socket);

        return (int) substr(strrchr((string) $name, ':'), 1);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function waitForHttpResponse(string $url): array
    {
        $deadline = microtime(true) + 10;
        $lastBody = '';

        while (microtime(true) < $deadline) {
            $context = stream_context_create([
                'http' => [
                    'ignore_errors' => true,
                    'timeout' => 1,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];

            if ($body !== false && $headers !== []) {
                return [$this->httpStatusCode($headers), $body];
            }

            $lastBody = $body === false ? '' : $body;
            usleep(100_000);
        }

        return [0, $lastBody];
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function httpStatusCode(array $headers): int
    {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0] ?? '', $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function removeDirectory(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $fileInfo->isLink() || $fileInfo->isFile()
                ? unlink($fileInfo->getPathname())
                : rmdir($fileInfo->getPathname());
        }

        rmdir($path);
    }
}
