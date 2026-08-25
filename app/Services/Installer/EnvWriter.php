<?php

namespace App\Services\Installer;

use Illuminate\Filesystem\Filesystem;

class EnvWriter
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array<string, string|null>  $values
     */
    public function write(array $values): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $path = base_path('.env');

        if (! $this->files->exists($path)) {
            $examplePath = base_path('.env.example');
            $this->files->put($path, $this->files->exists($examplePath) ? $this->files->get($examplePath) : '');
        }

        $contents = $this->files->get($path);

        foreach ($values as $key => $value) {
            $contents = $this->setValue($contents, $key, $value);
        }

        $this->files->put($path, $contents);
    }

    private function setValue(string $contents, string $key, ?string $value): string
    {
        $line = $key.'='.$this->formatValue($value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents) === 1) {
            return (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    private function formatValue(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value === '' || preg_match('/\s|#|"|\'|=/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
