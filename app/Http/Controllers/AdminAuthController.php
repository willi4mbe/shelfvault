<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Installer\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function show(InstallationState $installationState): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (Auth::check()) {
            return redirect()->route('admin');
        }

        return view('admin.login');
    }

    public function store(Request $request, InstallationState $installationState): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (Auth::check()) {
            return redirect()->route('admin');
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('login', $data['identifier'])
            ->orWhere('email', $data['identifier'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => __('admin.auth.failed'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        app()->setLocale($this->resolvedLocale($user->preferred_locale));

        return redirect()->intended(route('admin'));
    }

    public function destroy(Request $request, InstallationState $installationState): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolvedLocale(?string $locale): string
    {
        if (is_string($locale) && array_key_exists($locale, config('shelfvault.locales'))) {
            return $locale;
        }

        return config('app.locale', 'en');
    }
}
