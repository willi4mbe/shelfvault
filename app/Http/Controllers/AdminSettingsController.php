<?php

namespace App\Http\Controllers;

use App\Services\Installer\InstallationState;
use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        ]);
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
                'tone' => 'amber',
                'configured' => $this->filledConfig('services.omdb.api_key'),
                'variables' => ['OMDB_API_KEY'],
                'planned' => true,
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
