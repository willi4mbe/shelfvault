<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            ->assertSee('TMDB_API_KEY')
            ->assertSee('IGDB_CLIENT_SECRET')
            ->assertSee('TRANSLATION_PROVIDER')
            ->assertSee('GOOGLE_TRANSLATE_API_KEY')
            ->assertSee('Google Cloud Translation')
            ->assertSee('OMDB_API_KEY')
            ->assertSee(__('admin.settings.states.configured'))
            ->assertDontSee('tmdb-secret-value')
            ->assertDontSee('igdb-client-secret')
            ->assertDontSee('google-secret-value')
            ->assertDontSee('omdb-secret-value');
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
