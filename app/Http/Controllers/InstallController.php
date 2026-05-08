<?php

namespace App\Http\Controllers;

use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvironmentChecker;
use App\Services\Installer\InstallationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function show(EnvironmentChecker $checker): View
    {
        return view('install.requirements', [
            'requirements' => $checker->requirements(),
            'writablePaths' => $checker->writablePaths(),
            'passes' => $checker->passes(),
            'locales' => config('shelfvault.locales'),
        ]);
    }

    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('shelfvault.locales')))],
        ]);

        $request->session()->put('install.locale', $data['locale']);
        app()->setLocale($data['locale']);

        return redirect()->to($request->headers->get('referer', route('install.show')));
    }

    public function database(Request $request): View
    {
        return view('install.database', [
            'database' => $request->session()->get('install.database', [
                'connection' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'shelfvault',
                'username' => 'shelfvault',
                'password' => '',
            ]),
        ]);
    }

    public function storeDatabase(Request $request, DatabaseConnectionTester $databaseTester): RedirectResponse
    {
        $data = $request->validate($this->databaseRules());
        $error = $databaseTester->test($data);

        if ($error !== null) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['database_connection' => $error]);
        }

        $request->session()->put('install.database', $data);

        return redirect()->route('install.admin');
    }

    public function admin(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        return view('install.admin', [
            'locales' => config('shelfvault.locales'),
            'settings' => [
                'app_name' => 'ShelfVault',
                'app_url' => config('app.url', 'http://localhost'),
            ],
        ]);
    }

    public function complete(Request $request, InstallationManager $installationManager): RedirectResponse
    {
        if (! $request->session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:12'],
            'preferred_locale' => ['required', Rule::in(array_keys(config('shelfvault.locales')))],
            'app_name' => ['required', 'string', 'max:80'],
            'app_url' => ['required', 'url', 'max:255'],
        ], [], [
            'preferred_locale' => __('install.fields.admin_language'),
        ]);

        $data = $validator->validate();
        $appLocale = $data['preferred_locale'] ?: app()->getLocale();

        $installationManager->install(
            $request->session()->get('install.database'),
            [
                'login' => $data['login'],
                'email' => $data['email'],
                'password' => $data['password'],
                'preferred_locale' => $data['preferred_locale'],
            ],
            [
                'app_name' => $data['app_name'],
                'app_url' => $data['app_url'],
                'app_locale' => $appLocale,
            ],
        );

        $request->session()->forget('install');

        return redirect()->route('login');
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRules(): array
    {
        return [
            'connection' => ['required', Rule::in(['mysql', 'pgsql'])],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
