<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminCollectionTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-collection.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-collection.lock');

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

    public function test_guest_is_redirected_to_login_for_collection(): void
    {
        $this->get('/admin/collection')->assertRedirect(route('login'));
    }

    public function test_admin_can_view_empty_collection_list(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Collection')
            ->assertSee('Add item')
            ->assertSee('Your collection is empty.')
            ->assertSee('Back to dashboard')
            ->assertDontSee('Soon')
            ->assertDontSee('Bientôt');
    }

    public function test_admin_can_view_empty_collection_list_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Collection')
            ->assertSee('Ajouter un objet')
            ->assertSee('Votre collection est vide.')
            ->assertSee('Retour au tableau de bord');
    }

    public function test_collection_list_renders_a_linked_title_and_no_apply_button(): void
    {
        $item = Item::factory()->film()->create(['title' => 'The Matrix']);

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee(route('admin.collection.edit', $item), false)
            ->assertSee('The Matrix')
            ->assertSee('aria-label="Edit"', false)
            ->assertSee('aria-label="Delete"', false)
            ->assertDontSee('Apply filters')
            ->assertDontSee('Appliquer les filtres');
    }

    public function test_collection_introduction_avoids_the_word_manually(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertDontSee('manually')
            ->assertDontSee('manuellement');
    }

    public function test_admin_can_create_a_film_item(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'The Matrix',
                'release_year' => 1999,
                'barcode' => '1234567890123',
                'physical_format' => 'blu_ray',
                'condition' => 'very_good',
                'location' => 'Living room',
                'status' => 'owned',
                'is_favorite' => '1',
                'runtime_minutes' => 136,
                'director' => 'Lana Wachowski',
                'studio' => 'Warner Bros.',
                'age_rating' => 'R',
                'genres' => 'Action, Science fiction, ,',
                'cast_members' => 'Keanu Reeves, Carrie-Anne Moss',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('The Matrix was added.');

        $item = Item::query()->first();

        $this->assertSame('film', $item->type->value);
        $this->assertSame('The Matrix', $item->title);
        $this->assertSame(['Action', 'Science fiction'], $item->genres);
        $this->assertSame(['Keanu Reeves', 'Carrie-Anne Moss'], $item->cast_members);
        $this->assertSame(136, $item->runtime_minutes);
    }

    public function test_collection_list_does_not_show_year_column(): void
    {
        Item::factory()->film()->create([
            'title' => 'No year table',
            'release_year' => 2001,
        ]);

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertDontSee('Year')
            ->assertDontSee('Année')
            ->assertDontSee('2001');
    }

    public function test_admin_can_reject_an_invalid_physical_format_for_the_selected_type(): void
    {
        $this->actingAs(User::query()->first())
            ->from('/admin/collection/create')
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Invalid format film',
                'status' => 'owned',
                'physical_format' => 'cartridge',
            ])
            ->assertRedirect('/admin/collection/create')
            ->assertSessionHasErrors('physical_format');

        $this->assertDatabaseMissing('items', [
            'title' => 'Invalid format film',
        ]);
    }

    public function test_collection_requires_the_type_title_status_condition_and_physical_format_fields(): void
    {
        $cases = [
            ['field' => 'type', 'payload' => ['title' => 'Missing type', 'status' => 'owned', 'condition' => 'good', 'physical_format' => 'dvd'], 'message' => 'The type is required.'],
            ['field' => 'title', 'payload' => ['type' => 'film', 'status' => 'owned', 'condition' => 'good', 'physical_format' => 'dvd'], 'message' => 'The title is required.'],
            ['field' => 'status', 'payload' => ['type' => 'film', 'title' => 'Missing status', 'condition' => 'good', 'physical_format' => 'dvd'], 'message' => 'The status is required.'],
            ['field' => 'condition', 'payload' => ['type' => 'film', 'title' => 'Missing condition', 'status' => 'owned', 'physical_format' => 'dvd'], 'message' => 'The condition is required.'],
            ['field' => 'physical_format', 'payload' => ['type' => 'film', 'title' => 'Missing format', 'status' => 'owned', 'condition' => 'good'], 'message' => 'The physical format is required.'],
        ];

        foreach ($cases as $case) {
            $this->actingAs(User::query()->first())
                ->from('/admin/collection/create')
                ->post('/admin/collection', $case['payload'])
                ->assertRedirect('/admin/collection/create')
                ->assertSessionHasErrors($case['field']);
        }
    }

    public function test_collection_requires_the_type_title_status_condition_and_physical_format_fields_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->from('/admin/collection/create')
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Film sans format',
                'status' => 'owned',
                'condition' => 'good',
            ])
            ->assertRedirect('/admin/collection/create')
            ->assertSessionHasErrors('physical_format');
    }

    public function test_admin_can_create_a_video_game_item(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'video_game',
                'title' => 'Metroid Prime',
                'release_year' => 2002,
                'barcode' => '9876543210987',
                'physical_format' => 'disc',
                'condition' => 'good',
                'location' => 'Shelf B',
                'status' => 'owned',
                'platform' => 'Nintendo GameCube',
                'developer' => 'Retro Studios',
                'publisher' => 'Nintendo',
                'age_rating' => 'T',
                'genres' => 'Action, Adventure',
                'modes' => 'Single player,',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Metroid Prime was added.');

        $item = Item::query()->first();

        $this->assertSame('video_game', $item->type->value);
        $this->assertSame('Nintendo GameCube', $item->platform);
        $this->assertSame(['Action', 'Adventure'], $item->genres);
        $this->assertSame(['Single player'], $item->modes);
    }

    public function test_admin_can_create_a_board_game_item(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'board_game',
                'title' => 'Catan',
                'release_year' => 1995,
                'barcode' => '5555555555555',
                'physical_format' => 'box',
                'condition' => 'new',
                'location' => 'Cabinet',
                'status' => 'owned',
                'min_players' => 3,
                'max_players' => 4,
                'play_time_minutes' => 90,
                'designer' => 'Klaus Teuber',
                'publisher' => 'Kosmos',
                'genres' => 'Strategy, Family',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Catan was added.');

        $item = Item::query()->first();

        $this->assertSame('board_game', $item->type->value);
        $this->assertSame(3, $item->min_players);
        $this->assertSame(4, $item->max_players);
        $this->assertSame(['Strategy', 'Family'], $item->genres);
    }

    public function test_edit_page_rehydrates_type_and_physical_format(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'Rehydrated title',
            'physical_format' => 'blu_ray',
        ]);

        $this->actingAs(User::query()->first())
            ->get("/admin/collection/{$item->id}/edit")
            ->assertOk()
            ->assertSee('data-initial-type="film"', false)
            ->assertSee('data-initial-physical-format="blu_ray"', false);
    }

    public function test_admin_can_edit_an_item(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'Original title',
            'genres' => ['Drama'],
            'cast_members' => ['Actor One'],
        ]);

        $this->actingAs(User::query()->first())
            ->put("/admin/collection/{$item->id}", [
                'type' => 'film',
                'title' => 'Updated title',
                'release_year' => 2001,
                'barcode' => '1111111111111',
                'physical_format' => 'dvd',
                'condition' => 'good',
                'location' => 'Shelf C',
                'status' => 'loaned',
                'runtime_minutes' => 120,
                'director' => 'Director Name',
                'studio' => 'Studio Name',
                'age_rating' => 'PG-13',
                'genres' => 'Drama, Thriller',
                'cast_members' => 'Actor One, Actor Two',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Updated title was updated.');

        $item->refresh();

        $this->assertSame('Updated title', $item->title);
        $this->assertSame(['Drama', 'Thriller'], $item->genres);
        $this->assertSame(['Actor One', 'Actor Two'], $item->cast_members);
        $this->assertSame('loaned', $item->status->value);
    }

    public function test_admin_can_delete_an_item(): void
    {
        $item = Item::factory()->film()->create(['title' => 'Delete me']);

        $this->actingAs(User::query()->first())
            ->delete("/admin/collection/{$item->id}")
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('Delete me was deleted.');

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_admin_can_search_by_title_and_barcode(): void
    {
        Item::factory()->film()->create([
            'title' => 'The Matrix',
            'barcode' => '1234567890123',
        ]);
        Item::factory()->videoGame()->create([
            'title' => 'Metroid Prime',
            'barcode' => '9999999999999',
        ]);

        $response = $this->actingAs(User::query()->first())
            ->get('/admin/collection?q=Matrix');

        $response->assertOk()
            ->assertSee('The Matrix')
            ->assertDontSee('Metroid Prime');

        $this->actingAs(User::query()->first())
            ->get('/admin/collection?q=9999999999999')
            ->assertOk()
            ->assertSee('Metroid Prime')
            ->assertDontSee('The Matrix');
    }

    public function test_admin_can_filter_by_type_and_status(): void
    {
        Item::factory()->film()->owned()->create(['title' => 'Owned film']);
        Item::factory()->videoGame()->loaned()->create(['title' => 'Loaned game']);
        Item::factory()->boardGame()->archived()->create(['title' => 'Archived game']);

        $this->actingAs(User::query()->first())
            ->get('/admin/collection?type=film')
            ->assertOk()
            ->assertSee('Owned film')
            ->assertDontSee('Loaned game')
            ->assertDontSee('Archived game');

        $this->actingAs(User::query()->first())
            ->get('/admin/collection?status=loaned')
            ->assertOk()
            ->assertSee('Loaned game')
            ->assertDontSee('Owned film')
            ->assertDontSee('Archived game');
    }

    public function test_csv_fields_are_converted_to_json_arrays_and_rehydrated_for_editing(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'CSV film',
                'status' => 'owned',
                'condition' => 'good',
                'genres' => 'Action, Drama, , Thriller ',
                'cast_members' => 'Actor One, Actor Two, ,',
                'runtime_minutes' => 100,
                'director' => 'Director',
                'studio' => 'Studio',
                'age_rating' => 'PG',
                'physical_format' => 'dvd',
            ])
            ->assertRedirect();

        $item = Item::query()->first();

        $this->assertSame(['Action', 'Drama', 'Thriller'], $item->genres);
        $this->assertSame(['Actor One', 'Actor Two'], $item->cast_members);

        $this->actingAs(User::query()->first())
            ->get("/admin/collection/{$item->id}/edit")
            ->assertOk()
            ->assertSee('Action, Drama, Thriller')
            ->assertSee('Actor One, Actor Two');
    }

    public function test_status_options_do_not_expose_wanted(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee('Owned')
            ->assertSee('Loaned')
            ->assertSee('Archived')
            ->assertDontSee('Wanted');
    }

    public function test_collection_index_shows_success_message_after_update(): void
    {
        $item = Item::factory()->film()->create(['title' => 'Before']);

        $this->actingAs(User::query()->first())
            ->put("/admin/collection/{$item->id}", [
                'type' => 'film',
                'title' => 'After',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
            ])
            ->assertRedirect(route('admin.collection.index'))
            ->assertSessionHas('status', 'After was updated.');
    }

    public function test_collection_index_shows_success_message_after_creation_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Nouveau film',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
            ])
            ->assertRedirect(route('admin.collection.index'))
            ->assertSessionHas('status', 'Nouveau film a été ajouté.');
    }

    public function test_collection_actions_are_translated_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();
        Item::factory()->film()->create(['title' => 'Film français']);

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('aria-label="Modifier"', false)
            ->assertSee('aria-label="Supprimer"', false);
    }

    public function test_collection_pages_are_translated_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee('Ajouter un objet')
            ->assertSee('Retour à la collection')
            ->assertSee('Séparez les valeurs par des virgules')
            ->assertDontSee('Wanted');
    }
}
