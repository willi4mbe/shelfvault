<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockPath = storage_path('framework/testing/shelfvault-example-installed.lock');
        config(['shelfvault.installer.lock_path' => $this->lockPath]);
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::put($this->lockPath, now()->toIso8601String());
    }

    protected function tearDown(): void
    {
        File::delete($this->lockPath);

        parent::tearDown();
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('ShelfVault');
    }

    public function test_the_admin_placeholder_returns_a_successful_response(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Administration coming later');
    }
}
