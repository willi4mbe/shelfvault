<?php

namespace App\Services\Installer;

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallationManager
{
    public function __construct(
        private readonly DatabaseConnectionTester $databaseTester,
        private readonly EnvWriter $envWriter,
        private readonly InstallationState $installationState,
    ) {}

    /**
     * @param  array<string, string>  $database
     * @param  array<string, string>  $admin
     * @param  array<string, string>  $settings
     */
    public function install(array $database, array $admin, array $settings): void
    {
        $connection = $this->configureRuntimeDatabase($database);
        $this->writeEnvironment($database, $settings);

        $exitCode = Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw ValidationException::withMessages([
                'installation' => __('install.errors.migration_failed'),
            ]);
        }

        try {
            $this->createAdminAccount($connection, $admin);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'installation' => __('install.errors.admin_creation_failed'),
            ]);
        }

        if (! User::on($connection)->where('login', $admin['login'])->where('email', $admin['email'])->exists()) {
            throw ValidationException::withMessages([
                'installation' => __('install.errors.admin_creation_failed'),
            ]);
        }

        $this->ensurePublicStorageAccess();
        $this->installationState->lock();
    }

    /**
     * @param  array<string, string>  $database
     *
     * @return string
     */
    private function configureRuntimeDatabase(array $database): string
    {
        $connection = $database['connection'];
        $config = $this->databaseTester->connectionConfig($database);

        config([
            'database.default' => $connection,
            "database.connections.{$connection}" => $config,
        ]);

        DB::setDefaultConnection($connection);
        DB::purge($connection);
        DB::reconnect($connection);

        return $connection;
    }

    /**
     * @param  array<string, string>  $admin
     */
    protected function createAdminAccount(string $connection, array $admin): void
    {
        try {
            DB::connection($connection)->transaction(function () use ($connection, $admin): void {
                if (User::on($connection)->exists()) {
                    throw ValidationException::withMessages([
                        'login' => __('install.errors.admin_exists'),
                    ]);
                }

                User::on($connection)->create([
                    'name' => $admin['login'],
                    'login' => $admin['login'],
                    'email' => $admin['email'],
                    'password' => $admin['password'],
                    'preferred_locale' => $admin['preferred_locale'],
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'installation' => __('install.errors.admin_creation_failed'),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $database
     * @param  array<string, string>  $settings
     */
    private function writeEnvironment(array $database, array $settings): void
    {
        $this->envWriter->write([
            'APP_NAME' => $settings['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_DEBUG' => 'false',
            'APP_URL' => $settings['app_url'],
            'APP_LOCALE' => $settings['app_locale'],
            'APP_FALLBACK_LOCALE' => 'en',
            'DB_CONNECTION' => $database['connection'],
            'DB_HOST' => $database['host'] ?? '',
            'DB_PORT' => $database['port'] ?? '',
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'] ?? '',
            'DB_PASSWORD' => $database['password'] ?? '',
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'SHELFVAULT_VERSION' => config('shelfvault.version'),
        ]);
    }

    private function ensurePublicStorageAccess(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            Artisan::call('storage:link', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
        } catch (Throwable $exception) {
            Log::warning('ShelfVault could not create the public storage link. The route fallback will serve public uploads.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

}
