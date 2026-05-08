<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockPath = storage_path('framework/testing/shelfvault-installed.lock');
        config(['shelfvault.installer.lock_path' => $this->lockPath]);
        File::delete($this->lockPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockPath);

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

        $this->get('/install')
            ->assertOk()
            ->assertSee('Configuration initiale')
            ->assertSee('Configurez votre bibliothèque physique')
            ->assertSee('Extension PHP : ctype')
            ->assertSee('Serveur prêt');
    }

    public function test_install_is_blocked_after_setup_lock_exists(): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::put($this->lockPath, now()->toIso8601String());

        $this->get('/install')->assertRedirect(route('admin.placeholder'));
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
        $this->withSession([
            'install.database' => [
                'connection' => 'sqlite',
                'database' => ':memory:',
            ],
        ])->post('/install/complete', [
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'preferred_locale' => 'fr',
            'app_name' => 'ShelfVault',
            'app_url' => 'http://localhost',
            'app_locale' => 'en',
        ])->assertRedirect(route('admin.placeholder'));

        $admin = User::query()->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->login);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame('fr', $admin->preferred_locale);
        $this->assertFileExists($this->lockPath);
    }
}
