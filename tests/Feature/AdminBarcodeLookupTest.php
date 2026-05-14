<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use App\Services\Metadata\Providers\LocalItemBarcodeLookupProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBarcodeLookupTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-barcode.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-barcode.lock');

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
            'barcode.providers' => [LocalItemBarcodeLookupProvider::class],
        ]);

        Storage::fake('public');
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

    public function test_guest_is_blocked_from_the_barcode_lookup_endpoint(): void
    {
        $this->postJson(route('admin.collection.barcode-lookup'), [
            'barcode' => '1234567890123',
        ])->assertUnauthorized();
    }

    public function test_admin_receives_a_normalized_no_source_response_in_english(): void
    {
        config(['barcode.providers' => []]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.barcode-lookup'), [
                'barcode' => '1234567890123',
            ])
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'source',
                'data',
            ])
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.barcode_source_unavailable'));
    }

    public function test_admin_receives_a_normalized_no_source_response_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();
        config(['barcode.providers' => []]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.barcode-lookup'), [
                'barcode' => '1234567890123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.barcode_source_unavailable'));
    }

    public function test_barcode_lookup_requires_a_barcode(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.barcode-lookup'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['barcode']);
    }

    public function test_admin_receives_local_collection_metadata_when_a_matching_barcode_exists(): void
    {
        Storage::disk('public')->put('covers/local-lookup.jpg', 'cover-bytes');

        Item::factory()->film()->create([
            'title' => 'Local lookup title',
            'original_title' => 'Original local lookup title',
            'release_year' => 2005,
            'barcode' => '1234567890123',
            'cover_path' => 'covers/local-lookup.jpg',
            'physical_format' => 'dvd',
            'edition' => 'Collector',
            'region' => 'B',
            'condition' => 'good',
            'location' => 'Shelf A',
            'status' => 'owned',
            'description' => 'Local description',
            'runtime_minutes' => 120,
            'director' => 'Director Name',
            'studio' => 'Studio Name',
            'genres' => ['Action'],
            'cast_members' => ['Actor One'],
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.barcode-lookup'), [
                'barcode' => '1234567890123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.title', 'Local lookup title')
            ->assertJsonPath('data.cover_path', 'covers/local-lookup.jpg')
            ->assertJsonPath('data.cover_url', Storage::disk('public')->url('covers/local-lookup.jpg'))
            ->assertJsonPath('data.description', 'Local description');
    }
}
