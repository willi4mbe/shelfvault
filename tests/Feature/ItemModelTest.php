<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ItemModelTest extends TestCase
{
    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/shelfvault-items.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-items.lock');

        File::ensureDirectoryExists(dirname($this->databasePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->databasePath);
        File::delete($this->lockPath);
        File::put($this->databasePath, '');

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
            'preferred_locale' => 'en',
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_film_item_uses_enum_and_json_casts(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'The Matrix',
            'status' => ItemStatus::Owned,
            'condition' => ItemCondition::VeryGood,
            'is_favorite' => true,
            'acquired_at' => '2024-01-12',
        ]);

        $this->assertSame(ItemType::Film, $item->type);
        $this->assertSame(ItemStatus::Owned, $item->status);
        $this->assertSame(ItemCondition::VeryGood, $item->condition);
        $this->assertTrue($item->is_favorite);
        $this->assertSame('2024-01-12', $item->acquired_at?->toDateString());
        $this->assertSame(['drama', 'action'], $item->genres);
        $this->assertCount(2, $item->cast_members);
        $this->assertSame('The Matrix', $item->title);
    }

    public function test_video_game_item_can_be_created_with_video_game_specific_fields(): void
    {
        $item = Item::factory()->videoGame()->create([
            'title' => 'Metroid Prime',
            'platform' => 'Nintendo Switch',
            'genres' => ['adventure', 'action'],
            'modes' => ['single_player'],
        ]);

        $this->assertSame(ItemType::VideoGame, $item->type);
        $this->assertSame('Nintendo Switch', $item->platform);
        $this->assertSame(['adventure', 'action'], $item->genres);
        $this->assertSame(['single_player'], $item->modes);
        $this->assertNotNull($item->external_igdb_id);
    }

    public function test_board_game_item_can_be_created_with_board_game_specific_fields(): void
    {
        $item = Item::factory()->boardGame()->create([
            'title' => 'Catan',
            'min_players' => 3,
            'max_players' => 4,
            'play_time_minutes' => 90,
            'genres' => ['strategy', 'family'],
        ]);

        $this->assertSame(ItemType::BoardGame, $item->type);
        $this->assertSame(3, $item->min_players);
        $this->assertSame(4, $item->max_players);
        $this->assertSame(90, $item->play_time_minutes);
        $this->assertSame(['strategy', 'family'], $item->genres);
    }

    public function test_item_scopes_filter_items_by_type_and_status(): void
    {
        Item::factory()->film()->owned()->create(['title' => 'Film A']);
        Item::factory()->film()->loaned()->create(['title' => 'Film B']);
        Item::factory()->videoGame()->archived()->create(['title' => 'Game A']);
        Item::factory()->boardGame()->wanted()->create(['title' => 'Game B']);

        $this->assertSame(2, Item::film()->count());
        $this->assertSame(1, Item::videoGame()->count());
        $this->assertSame(1, Item::boardGame()->count());
        $this->assertSame(1, Item::owned()->count());
        $this->assertSame(1, Item::loaned()->count());
        $this->assertSame(1, Item::archived()->count());
        $this->assertSame(4, Item::recent()->count());
    }
}
