<?php

namespace App\Services\Installer;

use Illuminate\Filesystem\Filesystem;

class InstallationState
{
    public function __construct(private readonly Filesystem $files) {}

    public function installed(): bool
    {
        return $this->files->exists($this->lockPath());
    }

    public function lock(): void
    {
        $path = $this->lockPath();
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, now()->toIso8601String().PHP_EOL);
    }

    public function lockPath(): string
    {
        return (string) config('shelfvault.installer.lock_path');
    }
}
