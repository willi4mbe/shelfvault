<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-admin.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-admin.lock');

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
            'preferred_locale' => 'fr',
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_login_page_is_accessible_after_installation(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Connexion admin ShelfVault')
            ->assertSee('Login ou email')
            ->assertSee('Accueil');
    }

    public function test_login_via_login_succeeds(): void
    {
        $this->post('/admin/login', [
            'identifier' => 'admin',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('admin'));

        $this->assertAuthenticated();
    }

    public function test_login_via_email_succeeds(): void
    {
        $this->post('/admin/login', [
            'identifier' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('admin'));

        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_are_rejected_with_a_translated_message(): void
    {
        app()->setLocale('fr');

        $this->from('/admin/login')->post('/admin/login', [
            'identifier' => 'admin',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'identifier' => __('admin.auth.failed'),
        ]);
    }

    public function test_authenticated_user_is_redirected_from_login_to_admin(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/login')
            ->assertRedirect(route('admin'));
    }

    public function test_logout_works(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_dashboard_is_protected_and_uses_the_admin_locale(): void
    {
        $this->post('/admin/login', [
            'identifier' => 'admin',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('admin'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Accès rapides')
            ->assertSee('Vue d’ensemble')
            ->assertSee('Activité récente')
            ->assertSee('État de la configuration')
            ->assertSee('Accueil')
            ->assertSee('Aucune activité pour le moment.')
            ->assertSee('Bientôt')
            ->assertSee('Déplacer le bloc')
            ->assertSee('Réduire le bloc')
            ->assertDontSee('Liste de souhaits')
            ->assertDontSee('Gérez votre bibliothèque physique depuis un espace privé.')
            ->assertDontSee('Espace privé de pilotage de la collection.')
            ->assertSee('Se déconnecter');
    }

    public function test_admin_dashboard_can_render_in_english(): void
    {
        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'en'])->save();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Quick access')
            ->assertSee('Library overview')
            ->assertSee('Recent activity')
            ->assertSee('Setup status')
            ->assertSee('Home')
            ->assertSee('No activity yet.')
            ->assertSee('Soon')
            ->assertSee('Move block')
            ->assertSee('Collapse block')
            ->assertDontSee('Wishlist')
            ->assertDontSee('Manage your physical library from a private workspace.')
            ->assertDontSee('Private control room for the collection.')
            ->assertSee('Log out');
    }

    public function test_admin_dashboard_can_render_in_french(): void
    {
        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Accès rapides')
            ->assertSee('Vue d’ensemble')
            ->assertSee('Activité récente')
            ->assertSee('État de la configuration')
            ->assertSee('Accueil')
            ->assertSee('Aucune activité pour le moment.')
            ->assertSee('Bientôt')
            ->assertSee('Déplacer le bloc')
            ->assertSee('Réduire le bloc')
            ->assertDontSee('Liste de souhaits')
            ->assertDontSee('Gérez votre bibliothèque physique depuis un espace privé.')
            ->assertDontSee('Espace privé de pilotage de la collection.')
            ->assertSee('Se déconnecter');
    }
}
