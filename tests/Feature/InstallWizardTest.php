<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\InstallationManager;
use App\Services\Installer\InstallationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    private string $defaultLockPath;

    private string $lockPath;

    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->lockPath = storage_path('framework/testing/shelfvault-installed.lock');
        $this->sqlitePath = storage_path('framework/testing/shelfvault-install.sqlite');

        File::delete($this->defaultLockPath);
        File::ensureDirectoryExists(dirname($this->sqlitePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->sqlitePath);
        File::put($this->sqlitePath, '');
        File::delete($this->lockPath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sqlitePath,
            'shelfvault.installer.lock_path' => $this->lockPath,
            'session.driver' => 'file',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->lockPath);
        File::delete($this->sqlitePath);

        parent::tearDown();
    }

    public function test_application_routes_redirect_to_install_before_setup(): void
    {
        $this->get('/')->assertRedirect(route('install.show'));
        $this->get('/admin')->assertRedirect(route('install.show'));
    }

    public function test_install_requirements_page_uses_default_english_copy_and_logo(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('Initial setup')
            ->assertSee('Server ready')
            ->assertSee('English')
            ->assertSee('Set up your physical library')
            ->assertSee('PHP extension: ctype')
            ->assertSee('Installed')
            ->assertSee('branding/shelfvault.png', false);
    }

    public function test_install_locale_switch_persists_in_session_and_updates_copy(): void
    {
        $this->post('/install/locale', [
            'locale' => 'fr',
        ])->assertRedirect(route('install.show'));

        $this->assertSame('fr', session('install.locale'));

        $this->withSession([
            'install.database' => [
                'connection' => 'sqlite',
                'database' => $this->sqlitePath,
            ],
        ])->get('/install/admin')
            ->assertOk()
            ->assertSee('Langue de l’admin')
            ->assertDontSee('Langue par défaut')
            ->assertDontSee('Default language');
    }

    public function test_install_is_blocked_after_setup_lock_exists(): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::put($this->lockPath, now()->toIso8601String());

        $this->get('/install')->assertRedirect(route('login'));
    }

    public function test_invalid_database_credentials_return_a_useful_error(): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock): void {
            $mock->shouldReceive('test')
                ->once()
                ->andReturn('ShelfVault could not connect to the database. Check the details and try again.');
        });

        $this->post('/install/database', [
            'connection' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'shelfvault',
            'username' => 'shelfvault',
            'password' => 'bad-password',
        ])->assertSessionHasErrors('database_connection');
    }

    public function test_installation_creates_admin_and_lock_file(): void
    {
        File::ensureDirectoryExists(dirname($this->sqlitePath));

        $this->withSession([
            'install.database' => [
                'connection' => 'sqlite',
                'database' => $this->sqlitePath,
                'host' => '',
                'port' => '',
                'username' => '',
                'password' => '',
            ],
        ])->post('/install/complete', [
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'preferred_locale' => 'fr',
            'app_name' => 'ShelfVault',
            'app_url' => 'http://localhost',
        ])->assertRedirect(route('login'));

        $admins = User::on('sqlite')->get();

        $this->assertCount(1, $admins);

        $admin = $admins->first();

        $this->assertSame('admin', $admin->login);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame('fr', $admin->preferred_locale);
        $this->assertSame(1, User::on('sqlite')->count());
        $this->assertFileExists($this->lockPath);
    }

    public function test_installation_returns_a_clear_error_and_skips_lock_when_admin_creation_fails(): void
    {
        File::ensureDirectoryExists(dirname($this->sqlitePath));

        $manager = Mockery::mock(
            InstallationManager::class,
            [
                $this->app->make(DatabaseConnectionTester::class),
                $this->app->make(EnvWriter::class),
                $this->app->make(InstallationState::class),
            ],
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $manager->shouldReceive('createAdminAccount')
            ->once()
            ->andThrow(new RuntimeException('simulated admin creation failure'));

        $this->app->instance(InstallationManager::class, $manager);

        $this->withSession([
            'install.database' => [
                'connection' => 'sqlite',
                'database' => $this->sqlitePath,
                'host' => '',
                'port' => '',
                'username' => '',
                'password' => '',
            ],
        ])->post('/install/complete', [
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'preferred_locale' => 'fr',
            'app_name' => 'ShelfVault',
            'app_url' => 'http://localhost',
        ])->assertRedirect()->assertSessionHasErrors('installation');

        $this->assertFileDoesNotExist($this->lockPath);
        $this->assertSame(0, User::on('sqlite')->count());
    }
}
