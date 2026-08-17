<?php

namespace App\Http\Controllers;

use App\Services\Installer\InstallationState;
use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(
        InstallationState $installationState,
        Request $request,
        AdminNavigation $navigation,
        TextTranslationProvider $translationProvider,
    ): RedirectResponse|View {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('admin.settings.index', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'integrations' => $this->integrations($translationProvider),
            'locales' => config('shelfvault.locales'),
            'currentLocale' => Auth::user()?->preferred_locale ?? config('app.locale', 'en'),
        ]);
    }

    public function update(InstallationState $installationState, Request $request): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'preferred_locale' => ['required', Rule::in(array_keys(config('shelfvault.locales')))],
        ], [], [
            'preferred_locale' => __('admin.settings.language_field'),
        ]);

        $user = Auth::user();
        $user->forceFill([
            'preferred_locale' => $validated['preferred_locale'],
        ])->save();

        app()->setLocale($validated['preferred_locale']);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('admin.settings.saved'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function integrations(TextTranslationProvider $translationProvider): array
    {
        return [
            [
                'key' => 'tmdb',
                'icon' => 'films',
                'tone' => 'violet',
                'configured' => $this->filledConfig('services.tmdb.api_key') || $this->filledConfig('services.tmdb.bearer_token'),
                'variables' => ['TMDB_API_KEY', 'TMDB_BEARER_TOKEN', 'TMDB_LANGUAGE', 'TMDB_REGION'],
            ],
            [
                'key' => 'igdb',
                'icon' => 'video_games',
                'tone' => 'emerald',
                'configured' => $this->filledConfig('services.igdb.client_id')
                    && ($this->filledConfig('services.igdb.access_token') || $this->filledConfig('services.igdb.client_secret')),
                'variables' => ['IGDB_CLIENT_ID', 'IGDB_CLIENT_SECRET', 'IGDB_ACCESS_TOKEN'],
            ],
            [
                'key' => 'translation',
                'icon' => 'locale',
                'tone' => 'sky',
                'configured' => $translationProvider->configured(),
                'variables' => [
                    'TRANSLATION_PROVIDER',
                    'TRANSLATION_SOURCE_LOCALE',
                    'GOOGLE_TRANSLATE_API_KEY',
                    'GOOGLE_TRANSLATE_BASE_URL',
                ],
                'provider' => $this->translationProviderName(),
                'api_key_configured' => $this->filledConfig('services.translation.google.api_key'),
            ],
            [
                'key' => 'omdb',
                'icon' => 'external_link',
                'tone' => 'slate',
                'configured' => $this->filledConfig('services.omdb.api_key'),
                'variables' => ['OMDB_API_KEY'],
                'optional_future' => true,
            ],
        ];
    }

    private function filledConfig(string $key): bool
    {
        return trim((string) config($key, '')) !== '';
    }

    private function translationProviderName(): string
    {
        $provider = strtolower(trim((string) config('services.translation.provider', '')));

        return match ($provider) {
            'google' => __('admin.settings.translation_providers.google'),
            '' => __('admin.settings.translation_providers.none'),
            default => __('admin.settings.translation_providers.custom'),
        };
    }
}
