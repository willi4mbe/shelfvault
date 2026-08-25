<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function preview(): View
    {
        $this->abortUnlessLocalPreview();
        $this->setPreviewLocaleFromSession();

        $requirements = [
            [
                'name' => 'PHP',
                'required' => '8.3+',
                'current' => PHP_VERSION,
                'passes' => true,
                'kind' => 'version',
            ],
            [
                'name' => __('install.requirements.php_extension', ['extension' => 'pdo_mysql']),
                'required' => __('install.status.installed'),
                'current' => __('install.status.installed'),
                'passes' => true,
                'kind' => 'extension',
            ],
            [
                'name' => __('install.requirements.php_extension', ['extension' => 'mbstring']),
                'required' => __('install.status.installed'),
                'current' => __('install.status.installed'),
                'passes' => true,
                'kind' => 'extension',
            ],
            [
                'name' => __('install.requirements.php_extension', ['extension' => 'xml']),
                'required' => __('install.status.installed'),
                'current' => __('install.status.installed'),
                'passes' => true,
                'kind' => 'extension',
            ],
        ];

        $writablePaths = [
            ['name' => 'storage', 'path' => storage_path(), 'passes' => true],
            ['name' => 'public', 'path' => storage_path('app/public'), 'passes' => true],
            ['name' => 'cache', 'path' => base_path('bootstrap/cache'), 'passes' => true],
        ];

        return view('install.requirements', [
            'requirements' => $requirements,
            'writablePaths' => $writablePaths,
            'passes' => true,
        ]);
    }

    public function previewLocale(Request $request): RedirectResponse
    {
        $this->abortUnlessLocalPreview();

        $locale = (string) $request->input('locale', 'en');

        if (array_key_exists($locale, config('shelfvault.locales'))) {
            $request->session()->put('install.locale', $locale);
        }

        return redirect()->route('install.requirements');
    }

    public function previewRedirect(): RedirectResponse
    {
        $this->abortUnlessLocalPreview();

        return redirect()->route('install.requirements');
    }

    public function publicStorage(string $path)
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    private function abortUnlessLocalPreview(): void
    {
        abort_unless(in_array((string) config('app.env'), ['local', 'testing'], true), 404);
    }

    private function setPreviewLocaleFromSession(): void
    {
        $locale = (string) request()->session()->get('install.locale', app()->getLocale());

        if (array_key_exists($locale, config('shelfvault.locales'))) {
            app()->setLocale($locale);
        }
    }
}
