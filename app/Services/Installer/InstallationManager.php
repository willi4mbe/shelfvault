<?php

namespace App\Services\Installer;

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $this->configureRuntimeDatabase($database);
        $this->writeEnvironment($database, $settings);

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw ValidationException::withMessages([
                'installation' => __('install.errors.migration_failed'),
            ]);
        }

        DB::transaction(function () use ($admin): void {
            if (User::query()->exists()) {
                throw ValidationException::withMessages([
                    'login' => __('install.errors.admin_exists'),
                ]);
            }

            User::query()->create([
                'name' => $admin['login'],
                'login' => $admin['login'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'preferred_locale' => $admin['preferred_locale'],
            ]);
        });

        $this->installationState->lock();
    }

    /**
     * @param  array<string, string>  $database
     */
    private function configureRuntimeDatabase(array $database): void
    {
        $connection = $database['connection'];
        $config = $this->databaseTester->connectionConfig($database);

        config([
            'database.default' => $connection,
            "database.connections.{$connection}" => $config,
        ]);

        DB::purge($connection);
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
            'APP_KEY' => config('app.key') ?: 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
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
        ]);
    }
}
