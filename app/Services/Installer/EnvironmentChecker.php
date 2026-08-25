<?php

namespace App\Services\Installer;

class EnvironmentChecker
{
    /**
     * @return array<int, array{name: string, required: string, current: string, passes: bool, kind: string}>
     */
    public function requirements(): array
    {
        $extensions = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'];

        $checks = [
            [
                'name' => 'PHP',
                'required' => '8.3+',
                'current' => PHP_VERSION,
                'passes' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'kind' => 'version',
            ],
        ];

        foreach ($extensions as $extension) {
            $checks[] = [
                'name' => __('install.requirements.php_extension', ['extension' => $extension]),
                'required' => __('install.status.installed'),
                'current' => extension_loaded($extension)
                    ? __('install.status.installed')
                    : __('install.status.missing'),
                'passes' => extension_loaded($extension),
                'kind' => 'extension',
            ];
        }

        return $checks;
    }

    /**
     * @return array<int, array{name: string, path: string, passes: bool}>
     */
    public function writablePaths(): array
    {
        return collect([
            storage_path(),
            storage_path('app'),
            storage_path('app/public'),
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            base_path(),
        ])->map(fn (string $path): array => [
            'name' => basename($path) ?: $path,
            'path' => $path,
            'passes' => is_writable($path),
        ])->all();
    }

    public function passes(): bool
    {
        return collect($this->requirements())->every('passes')
            && collect($this->writablePaths())->every('passes');
    }
}
