<?php

namespace App\Http\Controllers;

use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Installer\InstallationState;
use App\Services\Library\LibrarySettings;
use App\Services\Updates\ReleaseCheckResult;
use App\Services\Updates\UpdateService;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminSettingsController extends Controller
{
    public function index(
        InstallationState $installationState,
        Request $request,
        AdminNavigation $navigation,
        ExternalServiceSettings $settings,
        LibrarySettings $librarySettings,
        UpdateService $updateService,
    ): RedirectResponse|View {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        return view('admin.settings.index', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'integrations' => $this->integrations($settings),
            'libraryName' => $librarySettings->libraryName(),
            'contentTypes' => $this->contentTypes($librarySettings),
            'loansEnabled' => $librarySettings->loansEnabled(),
            'accentColor' => $librarySettings->accentColor(),
            'accentColorOptions' => $this->accentColorOptions($librarySettings),
            'locationsEnabled' => $librarySettings->locationsEnabled(),
            'locationsText' => $librarySettings->locationsText(),
            'locales' => config('shelfvault.locales'),
            'currentLocale' => Auth::user()?->preferred_locale ?? config('app.locale', 'en'),
            'updatePanel' => $updateService->summary($request->session()->get('shelfvault.update_check')),
        ]);
    }

    public function updateLibrary(
        InstallationState $installationState,
        Request $request,
        LibrarySettings $librarySettings,
    ): RedirectResponse {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        $validated = $request->validate([
            'library_name' => ['required', 'string', 'max:80'],
            'enabled_types' => ['required', 'array', 'min:1'],
            'enabled_types.*' => ['required', Rule::in($librarySettings->allTypeValues())],
            'loans_enabled' => ['nullable', 'boolean'],
            'accent_color' => ['required', Rule::in(array_keys($librarySettings->accentColorOptions()))],
            'locations_enabled' => ['nullable', 'boolean'],
            'locations' => ['nullable', 'string', 'max:2000'],
            'preferred_locale' => ['required', Rule::in(array_keys(config('shelfvault.locales')))],
        ], [], [
            'library_name' => __('admin.settings.library.name_label'),
            'enabled_types' => __('admin.settings.library.types_title'),
            'loans_enabled' => __('admin.settings.features.loans_title'),
            'accent_color' => __('admin.settings.appearance.accent_label'),
            'locations_enabled' => __('admin.settings.locations.enabled_title'),
            'locations' => __('admin.settings.locations.list_label'),
            'preferred_locale' => __('admin.settings.language_field'),
        ]);

        $librarySettings->setLibraryName($validated['library_name']);
        $librarySettings->setEnabledTypes(array_values(array_unique($validated['enabled_types'])));
        $librarySettings->setLoansEnabled($request->boolean('loans_enabled'));
        $librarySettings->setAccentColor($validated['accent_color']);
        $librarySettings->setLocationsEnabled($request->boolean('locations_enabled'));
        $librarySettings->setLocations(preg_split('/\R/u', (string) ($validated['locations'] ?? '')) ?: []);

        $user = Auth::user();
        $user->forceFill([
            'preferred_locale' => $validated['preferred_locale'],
        ])->save();

        app()->setLocale($validated['preferred_locale']);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('admin.settings.saved'));
    }

    public function update(InstallationState $installationState, Request $request): RedirectResponse
    {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
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

    public function updateExternalService(
        InstallationState $installationState,
        Request $request,
        ExternalServiceSettings $settings,
        string $service,
    ): RedirectResponse {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        abort_unless(array_key_exists($service, $this->serviceDefinitions()), 404);

        $validated = $request->validate($this->validationRules($service), [], $this->validationAttributes($service));
        $payload = $validated['settings'] ?? [];
        $enabled = array_key_exists('enabled', $payload) ? (bool) $payload['enabled'] : true;

        $settings->set($service, 'enabled', $enabled ? '1' : '0', false);

        if ($enabled) {
            foreach ($this->serviceDefinitions()[$service]['fields'] as $field) {
                $key = $field['key'];
                $isSecret = (bool) ($field['secret'] ?? false);
                $value = $payload[$key] ?? null;

                if ($isSecret && trim((string) $value) === '') {
                    continue;
                }

                $settings->set($service, $key, is_string($value) ? $value : null, $isSecret);
            }
        }

        if ($service === 'igdb') {
            Cache::forget('shelfvault.igdb.access_token');
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('admin.settings.external_saved', [
                'service' => __('admin.settings.integrations.'.$service.'.title'),
            ]));
    }

    public function testExternalService(
        InstallationState $installationState,
        ExternalServiceSettings $settings,
        string $service,
    ): RedirectResponse {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        abort_unless(array_key_exists($service, $this->serviceDefinitions()), 404);

        [$ok, $message] = match ($service) {
            'tmdb' => $this->testTmdb($settings),
            'igdb' => $this->testIgdb($settings),
            'google_translation' => $this->testGoogleTranslation($settings),
            'bgg' => $this->testBgg($settings),
            default => [false, __('admin.settings.tests.not_available')],
        };

        return redirect()
            ->route('admin.settings.index')
            ->with($ok ? 'status' : 'settings_error', $message);
    }

    public function checkUpdates(InstallationState $installationState, UpdateService $updateService): RedirectResponse
    {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        $check = $updateService->check();
        session()->put('shelfvault.update_check', $check->toArray());

        if ($check->status === ReleaseCheckResult::STATUS_UNAVAILABLE) {
            return redirect()
                ->route('admin.settings.index')
                ->with('settings_error', __('admin.settings.updates.notifications.unavailable'));
        }

        $message = $check->updateAvailable()
            ? __('admin.settings.updates.notifications.available', ['version' => $check->release?->tagName])
            : __('admin.settings.updates.notifications.current');

        return redirect()
            ->route('admin.settings.index')
            ->with('status', $message);
    }

    public function prepareUpdate(InstallationState $installationState, UpdateService $updateService): RedirectResponse
    {
        if ($redirect = $this->guardAdmin($installationState)) {
            return $redirect;
        }

        $preparation = $updateService->prepare();
        session()->put('shelfvault.update_check', $preparation->check->toArray());

        if (! $preparation->ready) {
            return redirect()
                ->route('admin.settings.index')
                ->with('settings_error', __('admin.settings.updates.notifications.prepare_unavailable'));
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', __('admin.settings.updates.notifications.prepare_ready', [
                'version' => $preparation->check->release?->tagName,
            ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function integrations(ExternalServiceSettings $settings): array
    {
        return collect($this->serviceDefinitions())
            ->map(fn (array $definition, string $service): array => [
                ...$definition,
                'key' => $service,
                'enabled' => $this->serviceEnabled($service, $settings),
                'state' => $this->serviceState($service, $settings),
                'fields' => $this->fieldsForView($service, $definition['fields'], $settings),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, enabled: bool}>
     */
    private function contentTypes(LibrarySettings $librarySettings): array
    {
        $enabledTypes = $librarySettings->enabledTypes();

        return collect($librarySettings->allTypeValues())
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => __('admin.collection.types.'.$type),
                'enabled' => (bool) ($enabledTypes[$type] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, rgb: string}>
     */
    private function accentColorOptions(LibrarySettings $librarySettings): array
    {
        return collect($librarySettings->accentColorOptions())
            ->map(fn (array $theme, string $key): array => [
                'value' => $key,
                'label' => __('admin.settings.accent_colors.'.$key),
                'rgb' => $theme['rgb'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function serviceDefinitions(): array
    {
        return [
            'tmdb' => [
                'icon' => 'films',
                'tone' => 'violet',
                'variables' => ['TMDB_API_KEY', 'TMDB_BEARER_TOKEN', 'TMDB_LANGUAGE', 'TMDB_REGION'],
                'testable' => true,
                'fields' => [
                    ['key' => 'api_key', 'secret' => true, 'config' => 'services.tmdb.api_key'],
                    ['key' => 'bearer_token', 'secret' => true, 'config' => 'services.tmdb.bearer_token'],
                    ['key' => 'language', 'secret' => false, 'config' => 'services.tmdb.language', 'default' => 'fr-FR'],
                    ['key' => 'region', 'secret' => false, 'config' => 'services.tmdb.region', 'default' => 'FR'],
                ],
            ],
            'igdb' => [
                'icon' => 'video_games',
                'tone' => 'emerald',
                'variables' => ['IGDB_CLIENT_ID', 'IGDB_CLIENT_SECRET', 'IGDB_ACCESS_TOKEN'],
                'testable' => true,
                'fields' => [
                    ['key' => 'client_id', 'secret' => true, 'config' => 'services.igdb.client_id'],
                    ['key' => 'client_secret', 'secret' => true, 'config' => 'services.igdb.client_secret'],
                    ['key' => 'access_token', 'secret' => true, 'config' => 'services.igdb.access_token'],
                ],
            ],
            'google_translation' => [
                'icon' => 'locale',
                'tone' => 'sky',
                'variables' => ['TRANSLATION_PROVIDER', 'GOOGLE_TRANSLATE_API_KEY'],
                'testable' => true,
                'fields' => [
                    ['key' => 'provider', 'secret' => false, 'config' => 'services.translation.provider', 'type' => 'select'],
                    ['key' => 'api_key', 'secret' => true, 'config' => 'services.translation.google.api_key'],
                ],
            ],
            'bgg' => [
                'icon' => 'board_games',
                'tone' => 'amber',
                'variables' => ['BGG_TOKEN'],
                'testable' => true,
                'fields' => [
                    ['key' => 'token', 'secret' => true, 'config' => 'services.bgg.token'],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function fieldsForView(string $service, array $fields, ExternalServiceSettings $settings): array
    {
        return collect($fields)
            ->map(function (array $field) use ($service, $settings): array {
                $key = $field['key'];
                $fallback = config($field['config'], $field['default'] ?? null);
                $secret = (bool) ($field['secret'] ?? false);

                return [
                    ...$field,
                    'label' => __('admin.settings.fields.'.$service.'.'.$key),
                    'value' => $secret ? null : $settings->get($service, $key, $fallback),
                    'configured' => $secret
                        ? $settings->maskedSecret($service, $key, $fallback) !== null
                        : trim((string) $settings->get($service, $key, $fallback)) !== '',
                    'stored' => $settings->hasStoredValue($service, $key),
                    'placeholder' => $secret
                        ? __('admin.settings.secret_placeholder')
                        : (string) ($field['default'] ?? ''),
                ];
            })
            ->all();
    }

    private function serviceState(string $service, ExternalServiceSettings $settings): string
    {
        if (! $this->serviceEnabled($service, $settings)) {
            return 'missing';
        }

        if ($service === 'tmdb') {
            $hasApiKey = $this->filled($settings->getSecret('tmdb', 'api_key', config('services.tmdb.api_key')));
            $hasBearerToken = $this->filled($settings->getSecret('tmdb', 'bearer_token', config('services.tmdb.bearer_token')));

            return $hasApiKey || $hasBearerToken ? 'configured' : 'missing';
        }

        if ($service === 'igdb') {
            $clientId = $settings->getSecret('igdb', 'client_id', config('services.igdb.client_id'));
            $clientSecret = $settings->getSecret('igdb', 'client_secret', config('services.igdb.client_secret'));
            $accessToken = $settings->getSecret('igdb', 'access_token', config('services.igdb.access_token'));

            return $this->filled($clientId) && ($this->filled($clientSecret) || $this->filled($accessToken))
                ? 'configured'
                : 'missing';
        }

        if ($service === 'google_translation') {
            $provider = strtolower((string) $settings->get('google_translation', 'provider', config('services.translation.provider')));
            $apiKey = $settings->getSecret('google_translation', 'api_key', config('services.translation.google.api_key'));

            return $provider === 'google' && $this->filled($apiKey) ? 'configured' : 'missing';
        }

        if ($service === 'bgg') {
            $token = $settings->getSecret('bgg', 'token', config('services.bgg.token'));

            if (strtolower(trim((string) $token)) === 'pending') {
                return 'pending';
            }

            return $this->filled($token) ? 'configured' : 'missing';
        }

        return 'missing';
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(string $service): array
    {
        return [
            'settings.enabled' => ['nullable', 'boolean'],
            ...match ($service) {
                'tmdb' => [
                    'settings.api_key' => ['nullable', 'string', 'max:2000'],
                    'settings.bearer_token' => ['nullable', 'string', 'max:4000'],
                    'settings.language' => ['nullable', 'string', 'max:20'],
                    'settings.region' => ['nullable', 'string', 'max:10'],
                ],
                'igdb' => [
                    'settings.client_id' => ['nullable', 'string', 'max:2000'],
                    'settings.client_secret' => ['nullable', 'string', 'max:4000'],
                    'settings.access_token' => ['nullable', 'string', 'max:4000'],
                ],
                'google_translation' => [
                    'settings.provider' => ['nullable', Rule::in(['', 'google'])],
                    'settings.api_key' => ['nullable', 'string', 'max:4000'],
                ],
                'bgg' => [
                    'settings.token' => ['nullable', 'string', 'max:4000'],
                ],
                default => [],
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(string $service): array
    {
        return collect($this->serviceDefinitions()[$service]['fields'] ?? [])
            ->prepend(['key' => 'enabled'])
            ->mapWithKeys(fn (array $field): array => [
                'settings.'.$field['key'] => $field['key'] === 'enabled'
                    ? __('admin.settings.provider_enabled_label')
                    : __('admin.settings.fields.'.$service.'.'.$field['key']),
            ])
            ->all();
    }

    private function serviceEnabled(string $service, ExternalServiceSettings $settings): bool
    {
        $stored = $settings->get($service, 'enabled');

        if ($stored !== null) {
            return filter_var($stored, FILTER_VALIDATE_BOOL);
        }

        return $this->serviceHasCredentials($service, $settings);
    }

    private function serviceHasCredentials(string $service, ExternalServiceSettings $settings): bool
    {
        if ($service === 'tmdb') {
            return $this->filled($settings->getSecret('tmdb', 'api_key', config('services.tmdb.api_key')))
                || $this->filled($settings->getSecret('tmdb', 'bearer_token', config('services.tmdb.bearer_token')));
        }

        if ($service === 'igdb') {
            $clientId = $settings->getSecret('igdb', 'client_id', config('services.igdb.client_id'));
            $clientSecret = $settings->getSecret('igdb', 'client_secret', config('services.igdb.client_secret'));
            $accessToken = $settings->getSecret('igdb', 'access_token', config('services.igdb.access_token'));

            return $this->filled($clientId) && ($this->filled($clientSecret) || $this->filled($accessToken));
        }

        if ($service === 'google_translation') {
            $provider = strtolower((string) $settings->get('google_translation', 'provider', config('services.translation.provider')));
            $apiKey = $settings->getSecret('google_translation', 'api_key', config('services.translation.google.api_key'));

            return $provider === 'google' && $this->filled($apiKey);
        }

        if ($service === 'bgg') {
            return $this->filled($settings->getSecret('bgg', 'token', config('services.bgg.token')));
        }

        return false;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testTmdb(ExternalServiceSettings $settings): array
    {
        $apiKey = $settings->getSecret('tmdb', 'api_key', config('services.tmdb.api_key'));
        $bearerToken = $settings->getSecret('tmdb', 'bearer_token', config('services.tmdb.bearer_token'));

        if (! $this->filled($apiKey) && ! $this->filled($bearerToken)) {
            return [false, __('admin.settings.tests.missing_credentials')];
        }

        try {
            $client = Http::baseUrl('https://api.themoviedb.org/3')->acceptJson()->timeout(8);

            if ($this->filled($bearerToken)) {
                $client = $client->withToken((string) $bearerToken);
            }

            $response = $client->get('/configuration', array_filter([
                'api_key' => $this->filled($apiKey) ? $apiKey : null,
            ]));

            return $response->successful()
                ? [true, __('admin.settings.tests.success', ['service' => 'TMDb'])]
                : [false, __('admin.settings.tests.failed_status', ['service' => 'TMDb', 'status' => $response->status()])];
        } catch (Throwable) {
            return [false, __('admin.settings.tests.failed', ['service' => 'TMDb'])];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testIgdb(ExternalServiceSettings $settings): array
    {
        $clientId = $settings->getSecret('igdb', 'client_id', config('services.igdb.client_id'));
        $accessToken = $settings->getSecret('igdb', 'access_token', config('services.igdb.access_token'));

        if (! $this->filled($clientId) || ! $this->filled($accessToken)) {
            return [false, __('admin.settings.tests.igdb_requires_token')];
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.igdb.base_url'), '/'))
                ->acceptJson()
                ->withHeaders([
                    'Client-ID' => (string) $clientId,
                    'Authorization' => 'Bearer '.$accessToken,
                ])
                ->timeout(8)
                ->withBody('fields id,name; limit 1;', 'text/plain')
                ->post('/games');

            return $response->successful()
                ? [true, __('admin.settings.tests.success', ['service' => 'IGDB'])]
                : [false, __('admin.settings.tests.failed_status', ['service' => 'IGDB', 'status' => $response->status()])];
        } catch (Throwable) {
            return [false, __('admin.settings.tests.failed', ['service' => 'IGDB'])];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testGoogleTranslation(ExternalServiceSettings $settings): array
    {
        $provider = strtolower((string) $settings->get('google_translation', 'provider', config('services.translation.provider')));
        $apiKey = $settings->getSecret('google_translation', 'api_key', config('services.translation.google.api_key'));

        if ($provider !== 'google' || ! $this->filled($apiKey)) {
            return [false, __('admin.settings.tests.missing_credentials')];
        }

        try {
            $response = Http::timeout(8)
                ->asForm()
                ->withQueryParameters(['key' => $apiKey])
                ->post((string) config('services.translation.google.base_url'), [
                    'q' => 'ShelfVault',
                    'target' => 'fr',
                    'format' => 'text',
                ]);

            return $response->successful()
                ? [true, __('admin.settings.tests.success', ['service' => 'Google Translation'])]
                : [false, __('admin.settings.tests.failed_status', ['service' => 'Google Translation', 'status' => $response->status()])];
        } catch (Throwable) {
            return [false, __('admin.settings.tests.failed', ['service' => 'Google Translation'])];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testBgg(ExternalServiceSettings $settings): array
    {
        $token = $settings->getSecret('bgg', 'token', config('services.bgg.token'));

        if (! $this->filled($token) || strtolower(trim((string) $token)) === 'pending') {
            return [false, __('admin.settings.tests.bgg_pending')];
        }

        try {
            $response = Http::timeout(8)
                ->accept('application/xml,text/xml,*/*')
                ->get('https://boardgamegeek.com/xmlapi2/search', [
                    'query' => 'Azul',
                    'type' => 'boardgame',
                ]);

            return $response->successful()
                ? [true, __('admin.settings.tests.success', ['service' => 'BoardGameGeek'])]
                : [false, __('admin.settings.tests.failed_status', ['service' => 'BoardGameGeek', 'status' => $response->status()])];
        } catch (Throwable) {
            return [false, __('admin.settings.tests.failed', ['service' => 'BoardGameGeek'])];
        }
    }

    private function guardAdmin(InstallationState $installationState): ?RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return null;
    }

    private function filled(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }
}
