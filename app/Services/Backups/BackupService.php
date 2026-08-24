<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupService
{
    /**
     * @return array{path: string, filename: string, size: int}
     */
    public function create(string $reason = 'manual'): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to create backups.');
        }

        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory);

        $filename = 'shelfvault-backup-'.now()->format('Ymd-His').'-'.$this->slug($reason).'.zip';
        $path = $directory.'/'.$filename;

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create backup archive.');
        }

        $this->addFileIfExists($zip, $this->appRoot().'/.env', '.env');
        $this->addFileIfExists($zip, $this->appRoot().'/storage/app/shelfvault/installed.lock', 'installed.lock');
        $this->addDatabaseDump($zip);
        $this->addDirectoryIfExists($zip, $this->appRoot().'/storage/app/public', 'storage/app/public');

        $zip->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'size' => is_file($path) ? filesize($path) : 0,
        ];
    }

    public function backupDirectory(): string
    {
        return $this->appRoot().'/storage/app/shelfvault/backups';
    }

    private function appRoot(): string
    {
        return rtrim((string) config('shelfvault.updates.app_root', base_path()), '/');
    }

    private function addDatabaseDump(ZipArchive $zip): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $database = (string) config('database.connections.sqlite.database');

            if ($database !== ':memory:' && is_file($database)) {
                $this->addFileIfExists($zip, $database, 'database/database.sqlite');

                return;
            }
        }

        $zip->addFromString('database/database.sql', $this->buildPortableSqlDump());
    }

    private function buildPortableSqlDump(): string
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $lines = [
            '-- ShelfVault portable SQL backup',
            '-- Generated at '.now()->toIso8601String(),
            '',
        ];

        foreach ($schema->getTableListing() as $table) {
            $rows = $connection->table($table)->get();

            foreach ($rows as $row) {
                $values = (array) $row;

                if ($values === []) {
                    continue;
                }

                $columns = implode(', ', array_map(fn (string $column): string => $connection->getQueryGrammar()->wrap($column), array_keys($values)));
                $quoted = implode(', ', array_map(fn (mixed $value): string => $this->quoteSqlValue($value), array_values($values)));

                $lines[] = 'INSERT INTO '.$connection->getQueryGrammar()->wrapTable($table).' ('.$columns.') VALUES ('.$quoted.');';
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function quoteSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function addDirectoryIfExists(ZipArchive $zip, string $sourceDirectory, string $zipDirectory): void
    {
        if (! is_dir($sourceDirectory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $source = $fileInfo->getPathname();
            $relative = str_replace('\\', '/', substr($source, strlen($sourceDirectory) + 1));
            $target = trim($zipDirectory, '/').'/'.$relative;

            if ($fileInfo->isDir()) {
                $zip->addEmptyDir(rtrim($target, '/').'/');
            } elseif ($fileInfo->isFile()) {
                $zip->addFile($source, $target);
            }
        }
    }

    private function addFileIfExists(ZipArchive $zip, string $source, string $target): void
    {
        if (is_file($source)) {
            $zip->addFile($source, $target);
        }
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($value)) ?: 'backup';

        return trim($slug, '-') ?: 'backup';
    }
}
