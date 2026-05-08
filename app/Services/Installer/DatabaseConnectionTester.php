<?php

namespace App\Services\Installer;

use Illuminate\Database\DatabaseManager;
use Throwable;

class DatabaseConnectionTester
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function test(array $config): ?string
    {
        $name = 'shelfvault_installer';

        config(["database.connections.{$name}" => $this->connectionConfig($config)]);

        try {
            $this->database->purge($name);
            $this->database->connection($name)->getPdo();

            return null;
        } catch (Throwable $exception) {
            return $this->friendlyMessage($exception);
        } finally {
            $this->database->disconnect($name);
            $this->database->purge($name);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function connectionConfig(array $config): array
    {
        if (($config['connection'] ?? 'mysql') === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $config['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        if (($config['connection'] ?? 'mysql') === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'] ?? '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ];
    }

    private function friendlyMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'SQLSTATE[HY000] [2002]')) {
            return __('install.errors.database_host');
        }

        if (str_contains($message, 'Access denied')) {
            return __('install.errors.database_credentials');
        }

        if (str_contains($message, 'Unknown database')) {
            return __('install.errors.database_name');
        }

        return __('install.errors.database_generic');
    }
}
