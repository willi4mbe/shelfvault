<?php

namespace Tests\Feature;

use App\Models\ExternalServiceSetting;
use App\Models\User;
use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Library\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-settings.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-settings.lock');

        File::delete($this->defaultLockPath);
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->databasePath);
        File::delete($this->lockPath);
        File::put($this->databasePath, '');
        File::put($this->lockPath, now()->toIso8601String());

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'session.driver' => 'file',
            'session.files' => storage_path('framework/testing/sessions'),
            'shelfvault.installer.lock_path' => $this->lockPath,
            'services.tmdb.api_key' => '',
            'services.tmdb.bearer_token' => '',
            'services.igdb.client_id' => '',
            'services.igdb.client_secret' => '',
            'services.igdb.access_token' => '',
            'services.translation.provider' => '',
            'services.translation.source_locale' => 'en',
            'services.translation.google.api_key' => '',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
            'services.bgg.token' => '',
            'services.omdb.api_key' => '',
        ]);

        File::ensureDirectoryExists(config('session.files'));

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        User::query()->create([
            'name' => 'Admin',
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'preferred_locale' => 'en',
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_settings_page_lists_external_integrations_without_exposing_secret_values(): void
    {
        config([
            'services.tmdb.api_key' => 'tmdb-secret-value',
            'services.igdb.client_id' => 'igdb-client-id',
            'services.igdb.client_secret' => 'igdb-client-secret',
            'services.translation.provider' => 'google',
            'services.translation.google.api_key' => 'google-secret-value',
            'services.omdb.api_key' => 'omdb-secret-value',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(__('admin.settings.title'))
            ->assertSee(__('admin.settings.language_title'))
            ->assertSee(__('admin.settings.language_help'))
            ->assertSee('Google Cloud Translation')
            ->assertSee(__('admin.settings.states.configured'))
            ->assertSee(__('admin.settings.secret_configured_placeholder'))
            ->assertDontSee('TMDB_API_KEY')
            ->assertDontSee('IGDB_CLIENT_SECRET')
            ->assertDontSee('TRANSLATION_PROVIDER')
            ->assertDontSee('GOOGLE_TRANSLATE_API_KEY')
            ->assertDontSee('BGG_TOKEN')
            ->assertDontSee('OMDB_API_KEY')
            ->assertDontSee('tmdb-secret-value')
            ->assertDontSee('igdb-client-secret')
            ->assertDontSee('google-secret-value')
            ->assertDontSee('omdb-secret-value');
    }

    public function test_admin_can_store_external_service_secrets_encrypted_from_settings(): void
    {
        $this->actingAs(User::query()->first())
            ->put(route('admin.settings.external-services.update', 'tmdb'), [
                'settings' => [
                    'api_key' => 'tmdb-db-secret',
                    'bearer_token' => '',
                    'language' => 'en-US',
                    'region' => 'US',
                ],
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status');

        $apiKey = ExternalServiceSetting::query()
            ->where('service', 'tmdb')
            ->where('key', 'api_key')
            ->firstOrFail();

        $this->assertTrue($apiKey->is_secret);
        $this->assertNull($apiKey->value);
        $this->assertNotSame('tmdb-db-secret', $apiKey->encrypted_value);
        $this->assertSame('tmdb-db-secret', Crypt::decryptString((string) $apiKey->encrypted_value));

        $this->assertDatabaseHas('external_service_settings', [
            'service' => 'tmdb',
            'key' => 'language',
            'value' => 'en-US',
            'is_secret' => false,
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('en-US')
            ->assertSee('US')
            ->assertSee(__('admin.settings.states.configured'))
            ->assertDontSee('tmdb-db-secret');
    }

    public function test_blank_secret_fields_preserve_existing_secret_values(): void
    {
        app(ExternalServiceSettings::class)->set('igdb', 'client_id', 'existing-client', true);
        app(ExternalServiceSettings::class)->set('igdb', 'access_token', 'existing-token', true);

        $this->actingAs(User::query()->first())
            ->put(route('admin.settings.external-services.update', 'igdb'), [
                'settings' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'access_token' => '',
                ],
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(ExternalServiceSettings::class);

        $this->assertSame('existing-client', $settings->getSecret('igdb', 'client_id'));
        $this->assertSame('existing-token', $settings->getSecret('igdb', 'access_token'));
    }

    public function test_external_service_settings_fall_back_to_config_when_database_is_empty(): void
    {
        config(['services.tmdb.api_key' => 'fallback-tmdb-key']);

        $settings = app(ExternalServiceSettings::class);

        $this->assertSame('fallback-tmdb-key', $settings->getSecret('tmdb', 'api_key', config('services.tmdb.api_key')));

        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(__('admin.settings.states.configured'))
            ->assertDontSee('fallback-tmdb-key');
    }

    public function test_admin_can_test_tmdb_connection_without_exposing_credentials(): void
    {
        app(ExternalServiceSettings::class)->set('tmdb', 'api_key', 'tmdb-db-secret', true);

        Http::fake([
            'https://api.themoviedb.org/3/configuration*' => Http::response(['images' => []], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->post(route('admin.settings.external-services.test', 'tmdb'))
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', __('admin.settings.tests.success', ['service' => 'TMDb']));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api_key=tmdb-db-secret'));
    }

    public function test_admin_can_update_application_language_from_settings(): void
    {
        $admin = User::query()->first();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'preferred_locale' => 'fr',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', __('admin.settings.saved'));

        $this->assertSame('fr', $admin->refresh()->preferred_locale);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Langue de l’application');
    }

    public function test_admin_can_save_library_name_and_enabled_content_types(): void
    {
        $this->actingAs(User::query()->first())
            ->put(route('admin.settings.library.update'), [
                'library_name' => 'William Media Library',
                'enabled_types' => ['film', 'board_game'],
                'loans_enabled' => '1',
                'accent_color' => 'green',
                'locations_enabled' => '1',
                'locations' => "Salon\nSalle de jeux\nCuisine",
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status', __('admin.settings.saved'));

        $settings = app(LibrarySettings::class);

        $this->assertSame('William Media Library', $settings->libraryName());
        $this->assertTrue($settings->isTypeEnabled('film'));
        $this->assertTrue($settings->isTypeEnabled('board_game'));
        $this->assertFalse($settings->isTypeEnabled('tv_series'));
        $this->assertFalse($settings->isTypeEnabled('video_game'));
        $this->assertSame('green', $settings->accentColor());
        $this->assertTrue($settings->locationsEnabled());
        $this->assertSame(['Salon', 'Salle de jeux', 'Cuisine'], $settings->locations());

        $this->assertDatabaseHas('external_service_settings', [
            'service' => 'library',
            'key' => 'name',
            'value' => 'William Media Library',
            'is_secret' => false,
        ]);
        $this->assertDatabaseHas('external_service_settings', [
            'service' => 'library',
            'key' => 'accent_color',
            'value' => 'green',
            'is_secret' => false,
        ]);
        $this->assertDatabaseHas('external_service_settings', [
            'service' => 'library',
            'key' => 'locations',
            'value' => "Salon\nSalle de jeux\nCuisine",
            'is_secret' => false,
        ]);
    }

    public function test_settings_page_shows_accent_color_choices(): void
    {
        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(__('admin.settings.appearance_heading'))
            ->assertSee(__('admin.settings.appearance.accent_label'))
            ->assertSee(__('admin.settings.accent_colors.orange'))
            ->assertSee(__('admin.settings.accent_colors.yellow'))
            ->assertSee(__('admin.settings.accent_colors.green'))
            ->assertSee(__('admin.settings.accent_colors.red'));
    }

    public function test_loans_feature_toggle_controls_navigation_and_access(): void
    {
        app(LibrarySettings::class)->setLoansEnabled(false);

        $this->actingAs(User::query()->first())
            ->get(route('admin'))
            ->assertOk()
            ->assertDontSee(route('admin.loans.index'), false);

        $this->actingAs(User::query()->first())
            ->get(route('admin.loans.index'))
            ->assertNotFound();

        app(LibrarySettings::class)->setLoansEnabled(true);

        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(route('admin.loans.index'), false);

        $this->actingAs(User::query()->first())
            ->get(route('admin.loans.index'))
            ->assertOk()
            ->assertSee(__('admin.loans.title'));
    }

    public function test_settings_page_no_longer_shows_removed_admin_blocks(): void
    {
        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee(__('admin.settings.kicker'))
            ->assertDontSee(__('admin.settings.security_heading'))
            ->assertDontSee(__('admin.settings.next_title'));
    }

    public function test_settings_navigation_is_available_to_admins(): void
    {
        $this->actingAs(User::query()->first())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(route('admin.settings.index'), false)
            ->assertSee(__('admin.navigation.settings'));
    }
}
