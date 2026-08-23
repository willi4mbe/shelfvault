<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\Library\LibrarySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
            ->assertDontSee('href="'.route('admin.collection.show', $item).'"', false)
            ->assertSee('The Matrix')
            ->assertSee('aria-label="Edit"', false)
            ->assertSee('aria-label="Delete"', false)
            ->assertDontSee('Apply filters')
            ->assertDontSee('Appliquer les filtres');
    }

    public function test_collection_list_shows_a_cover_thumbnail_or_placeholder(): void
    {
        Storage::disk('public')->put('covers/list-cover.jpg', 'cover-bytes');

        $withCover = Item::factory()->film()->create([
            'title' => 'With cover',
            'cover_path' => 'covers/list-cover.jpg',
        ]);

        $withoutCover = Item::factory()->videoGame()->create([
            'title' => 'Without cover',
            'cover_path' => null,
        ]);

        $this->assertSame(
            Storage::disk('public')->url('covers/list-cover.jpg'),
            $withCover->fresh()->coverUrl(),
        );

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee(__('admin.collection.placeholders.no_cover'))
            ->assertSee($withCover->title)
            ->assertSee($withoutCover->title);
    }

    public function test_guest_is_redirected_to_login_for_item_detail(): void
    {
        $item = Item::factory()->film()->create(['title' => 'Guest hidden']);

        $this->get(route('admin.collection.show', $item))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_item_detail_in_english(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'The Matrix',
            'original_title' => 'The Matrix',
            'release_year' => 1999,
            'barcode' => '1234567890123',
            'description' => 'A hacker discovers the truth behind his simulated reality.',
            'physical_format' => 'blu_ray',
            'edition' => 'Collector',
            'region' => 'B',
            'condition' => 'very_good',
            'location' => 'Living room',
            'status' => 'owned',
            'is_favorite' => true,
            'acquired_at' => '2024-01-15',
            'personal_notes' => 'Private note',
            'runtime_minutes' => 136,
            'director' => 'Lana Wachowski',
            'studio' => 'Warner Bros.',
            'age_rating' => 'R',
            'genres' => ['Action', 'Science fiction'],
            'cast_members' => ['Keanu Reeves', 'Carrie-Anne Moss', 'Laurence Fishburne', 'Hugo Weaving', 'Gloria Foster', 'Joe Pantoliano'],
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee('Film overview')
            ->assertSee('Main information')
            ->assertSee('Physical details')
            ->assertSee('Metadata')
            ->assertSee('Notes')
            ->assertSee('The Matrix')
            ->assertSee('A hacker discovers the truth behind his simulated reality.')
            ->assertSee('Action')
            ->assertSee('Science fiction')
            ->assertSee('Lana Wachowski')
            ->assertSee('Warner Bros.')
            ->assertSee('136')
            ->assertSee('Keanu Reeves')
            ->assertSee('Carrie-Anne Moss')
            ->assertSee('Laurence Fishburne')
            ->assertSee('Hugo Weaving')
            ->assertSee('Gloria Foster')
            ->assertSee('+1 more')
            ->assertDontSee('Joe Pantoliano')
            ->assertSee('Living room')
            ->assertSee('Notes')
            ->assertSee('Private note');
    }

    public function test_admin_can_view_item_detail_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $item = Item::factory()->videoGame()->create([
            'title' => 'Metroid Prime',
            'physical_format' => 'disc',
            'platform' => 'Nintendo GameCube',
            'developer' => 'Retro Studios',
            'publisher' => 'Nintendo',
            'age_rating' => 'T',
            'genres' => ['Action', 'Aventure'],
            'modes' => ['Solo'],
            'personal_notes' => 'Note privée',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee('Informations principales')
            ->assertSee('Détails physiques')
            ->assertSee('Métadonnées')
            ->assertSee('Retour à la collection')
            ->assertSee('Metroid Prime')
            ->assertSee('Nintendo GameCube')
            ->assertSee('Retro Studios')
            ->assertSee('Solo')
            ->assertSee('Notes')
            ->assertSee('Note privée');
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
        $this->assertSame('very_good', $item->condition->value);
        $this->assertSame(['Action', 'Science fiction'], $item->genres);
        $this->assertSame(['Keanu Reeves', 'Carrie-Anne Moss'], $item->cast_members);
        $this->assertSame(136, $item->runtime_minutes);
    }

    public function test_admin_can_create_an_item_without_condition(): void
    {
        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'video_game',
                'title' => 'Digital game',
                'status' => 'owned',
                'physical_format' => 'digital_copy',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item = Item::query()->first();

        $this->assertSame('Digital game', $item->title);
        $this->assertNull($item->condition);
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

    public function test_disabled_content_types_are_hidden_from_creation_and_rejected_on_store(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film', 'board_game']);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.create'))
            ->assertOk()
            ->assertSee('value="film"', false)
            ->assertSee('value="board_game"', false)
            ->assertDontSee('value="video_game"', false)
            ->assertDontSee('value="tv_series"', false);

        $this->actingAs(User::query()->first())
            ->from(route('admin.collection.create'))
            ->post(route('admin.collection.store'), [
                'type' => 'video_game',
                'title' => 'Disabled game',
                'status' => 'owned',
                'physical_format' => 'disc',
            ])
            ->assertRedirect(route('admin.collection.create'))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('items', [
            'title' => 'Disabled game',
        ]);
    }

    public function test_existing_items_are_kept_when_their_type_is_disabled(): void
    {
        $item = Item::factory()->videoGame()->create(['title' => 'Existing game']);

        app(LibrarySettings::class)->setEnabledTypes(['film']);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'title' => 'Existing game',
            'type' => 'video_game',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.edit', $item))
            ->assertOk()
            ->assertSee('Existing game')
            ->assertSee('value="video_game"', false);
    }

    public function test_collection_requires_the_type_title_and_physical_format_fields(): void
    {
        $cases = [
            ['field' => 'type', 'payload' => ['title' => 'Missing type', 'status' => 'owned', 'condition' => 'good', 'physical_format' => 'dvd'], 'message' => 'The type is required.'],
            ['field' => 'title', 'payload' => ['type' => 'film', 'status' => 'owned', 'condition' => 'good', 'physical_format' => 'dvd'], 'message' => 'The title is required.'],
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

    public function test_collection_requires_the_type_title_status_and_physical_format_fields_in_french(): void
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

    public function test_admin_can_create_a_tv_series_item(): void
    {
        Storage::disk('public')->put('covers/tmdb-the-expanse.jpg', 'poster-bytes');

        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'tv_series',
                'title' => 'The Expanse',
                'original_title' => 'The Expanse',
                'description' => 'A police detective in the asteroid belt uncovers a system-wide conspiracy.',
                'release_year' => 2015,
                'end_year' => 2022,
                'barcode' => '3333333333333',
                'external_tmdb_id' => 63639,
                'cover_path' => 'covers/tmdb-the-expanse.jpg',
                'physical_format' => 'box_set',
                'condition' => 'very_good',
                'location' => 'Shelf TV',
                'status' => 'owned',
                'season_count' => 6,
                'episode_count' => 62,
                'runtime_minutes' => 45,
                'showrunner' => 'Naren Shankar',
                'network' => 'Syfy / Prime Video',
                'studio' => 'Alcon Television Group',
                'age_rating' => 'TV-14',
                'genres' => 'Science fiction, Drama',
                'cast_members' => 'Steven Strait, Shohreh Aghdashloo',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $this->actingAs(User::query()->first())
            ->get('/admin/collection')
            ->assertOk()
            ->assertSee('The Expanse was added.')
            ->assertSee(__('admin.collection.types.tv_series'));

        $item = Item::query()->first();

        $this->assertSame('tv_series', $item->type->value);
        $this->assertSame('The Expanse', $item->original_title);
        $this->assertSame('A police detective in the asteroid belt uncovers a system-wide conspiracy.', $item->description);
        $this->assertSame(2015, $item->release_year);
        $this->assertSame(2022, $item->end_year);
        $this->assertSame('63639', $item->external_tmdb_id);
        $this->assertSame('covers/tmdb-the-expanse.jpg', $item->cover_path);
        $this->assertSame(6, $item->season_count);
        $this->assertSame(62, $item->episode_count);
        $this->assertSame(45, $item->runtime_minutes);
        $this->assertSame('Naren Shankar', $item->showrunner);
        $this->assertSame('Syfy / Prime Video', $item->network);
        $this->assertSame('Alcon Television Group', $item->studio);
        $this->assertSame('TV-14', $item->age_rating);
        $this->assertSame(['Science fiction', 'Drama'], $item->genres);
        $this->assertSame(['Steven Strait', 'Shohreh Aghdashloo'], $item->cast_members);
        $this->assertSame(Storage::disk('public')->url('covers/tmdb-the-expanse.jpg'), $item->coverUrl());
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

    public function test_collection_forms_expose_a_unified_metadata_search_ui(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'TMDb form title',
        ]);

        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee(__('admin.collection.metadata.title_search'))
            ->assertSee(__('admin.collection.metadata.search_by_title_help'))
            ->assertSee(__('admin.collection.lookup.search'))
            ->assertSee(__('admin.collection.metadata.physical_fields_manual'))
            ->assertSeeInOrder([
                __('admin.collection.metadata.title_search'),
                __('admin.collection.fields.cover_image'),
                __('admin.collection.detail.sections.main'),
                __('admin.collection.detail.sections.physical'),
                __('admin.collection.detail.sections.metadata'),
                __('admin.collection.detail.sections.notes'),
            ])
            ->assertSeeInOrder([
                __('admin.collection.fields.title'),
                __('admin.collection.fields.original_title'),
                __('admin.collection.fields.release_year'),
                __('admin.collection.fields.barcode'),
            ])
            ->assertSee('type="button"', false)
            ->assertSee('@click="searchTitle()"', false)
            ->assertSee('name="external_tmdb_id"', false)
            ->assertSee('name="external_igdb_id"', false)
            ->assertDontSee('@click="searchBarcode({ force: true })"', false)
            ->assertDontSee('@click="openScanner()"', false)
            ->assertDontSee(__('admin.collection.scanner.scan'))
            ->assertDontSee(__('admin.collection.lookup.search_by_barcode'))
            ->assertDontSee(__('admin.collection.metadata.barcode_source_unavailable'))
            ->assertDontSee(__('admin.collection.scanner.manual_entry_available'))
            ->assertDontSee(__('admin.collection.metadata.search_title'))
            ->assertDontSee('admin-media-lookup-panel')
            ->assertDontSee('admin-media-scanner-panel')
            ->assertSee('name="barcode"', false);

        $this->actingAs(User::query()->first())
            ->get("/admin/collection/{$item->id}/edit")
            ->assertOk()
            ->assertSee(__('admin.collection.metadata.title_search'))
            ->assertSee(__('admin.collection.metadata.search_by_title_help'))
            ->assertSee(__('admin.collection.lookup.search'))
            ->assertSee(__('admin.collection.metadata.physical_fields_manual'))
            ->assertSeeInOrder([
                __('admin.collection.metadata.title_search'),
                __('admin.collection.fields.cover_image'),
            ])
            ->assertSeeInOrder([
                __('admin.collection.fields.title'),
                __('admin.collection.fields.original_title'),
                __('admin.collection.fields.release_year'),
                __('admin.collection.fields.barcode'),
            ])
            ->assertSee('type="button"', false)
            ->assertSee('@click="searchTitle()"', false)
            ->assertSee('name="external_tmdb_id"', false)
            ->assertSee('name="external_igdb_id"', false)
            ->assertDontSee('@click="searchBarcode({ force: true })"', false)
            ->assertDontSee('@click="openScanner()"', false)
            ->assertDontSee(__('admin.collection.scanner.scan'))
            ->assertDontSee(__('admin.collection.lookup.search_by_barcode'))
            ->assertDontSee(__('admin.collection.metadata.barcode_source_unavailable'))
            ->assertDontSee(__('admin.collection.scanner.manual_entry_available'))
            ->assertDontSee('admin-media-lookup-panel')
            ->assertDontSee('admin-media-scanner-panel')
            ->assertDontSee(__('admin.collection.metadata.search_title'));
    }

    public function test_collection_forms_show_the_simplified_search_ui_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee(__('admin.collection.metadata.title_search'))
            ->assertSee(__('admin.collection.metadata.search_by_title_help'))
            ->assertSee(__('admin.collection.lookup.search'))
            ->assertSee(__('admin.collection.fields.barcode'))
            ->assertDontSee(__('admin.collection.scanner.scan'))
            ->assertDontSee(__('admin.collection.lookup.search_by_barcode'))
            ->assertDontSee(__('admin.collection.metadata.barcode_source_unavailable'))
            ->assertDontSee(__('admin.collection.metadata.search_title'));
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
        $this->assertSame('good', $item->condition->value);
        $this->assertSame('owned', $item->status->value);
    }

    public function test_admin_can_remove_an_existing_condition_when_editing(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'Condition removed',
            'condition' => 'good',
            'physical_format' => 'dvd',
        ]);

        $this->actingAs(User::query()->first())
            ->put(route('admin.collection.update', $item), [
                'type' => 'film',
                'title' => 'Condition removed',
                'status' => 'owned',
                'condition' => 'Aucun',
                'physical_format' => 'dvd',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item->refresh();

        $this->assertNull($item->condition);
    }

    public function test_item_detail_handles_an_unspecified_condition(): void
    {
        $item = Item::factory()->film()->create([
            'title' => 'No condition detail',
            'condition' => null,
            'physical_format' => 'dvd',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee('Condition')
            ->assertSee(__('admin.collection.placeholders.not_specified'));
    }

    public function test_admin_can_change_type_without_losing_an_existing_cover(): void
    {
        Storage::disk('public')->put('covers/preserved-cover.jpg', 'cover-bytes');

        $item = Item::factory()->videoGame()->create([
            'title' => 'Switch type',
            'physical_format' => 'digital_copy',
            'cover_path' => 'covers/preserved-cover.jpg',
        ]);

        $this->actingAs(User::query()->first())
            ->put("/admin/collection/{$item->id}", [
                'type' => 'film',
                'title' => 'Switch type',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'digital_copy',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item->refresh();

        $this->assertSame('covers/preserved-cover.jpg', $item->cover_path);
        $this->assertTrue(Storage::disk('public')->exists('covers/preserved-cover.jpg'));
        $this->assertSame('film', $item->type->value);
        $this->assertSame('digital_copy', $item->physical_format);
    }

    public function test_admin_can_change_type_and_physical_format_without_losing_an_existing_cover(): void
    {
        Storage::disk('public')->put('covers/preserved-format-cover.jpg', 'cover-bytes');

        $item = Item::factory()->videoGame()->create([
            'title' => 'Switch type and format',
            'physical_format' => 'digital_copy',
            'cover_path' => 'covers/preserved-format-cover.jpg',
        ]);

        $this->actingAs(User::query()->first())
            ->put("/admin/collection/{$item->id}", [
                'type' => 'film',
                'title' => 'Switch type and format',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'blu_ray',
                'remove_cover' => '0',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item->refresh();

        $this->assertSame('covers/preserved-format-cover.jpg', $item->cover_path);
        $this->assertTrue(Storage::disk('public')->exists('covers/preserved-format-cover.jpg'));
        $this->assertSame('film', $item->type->value);
        $this->assertSame('blu_ray', $item->physical_format);
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

    public function test_admin_can_upload_a_cover_when_creating_an_item(): void
    {
        $cover = UploadedFile::fake()->image('cover.jpg', 600, 900);

        $this->actingAs(User::query()->first())
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Cover upload',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'cover_image' => $cover,
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item = Item::query()->first();

        $this->assertNotNull($item->cover_path);
        $this->assertStringStartsWith('covers/', $item->cover_path);
        $this->assertSame('dvd', $item->physical_format);
        $this->assertTrue(Storage::disk('public')->exists($item->cover_path));
    }

    public function test_admin_can_replace_an_existing_cover(): void
    {
        Storage::disk('public')->put('covers/original-cover.jpg', 'old-cover');

        $item = Item::factory()->film()->create([
            'title' => 'Replace cover',
            'physical_format' => 'dvd',
            'cover_path' => 'covers/original-cover.jpg',
        ]);

        $newCover = UploadedFile::fake()->image('new-cover.jpg', 500, 800);

        $this->actingAs(User::query()->first())
            ->put(route('admin.collection.update', $item), [
                'type' => 'film',
                'title' => 'Replace cover',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'cover_image' => $newCover,
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item->refresh();

        $this->assertNotSame('covers/original-cover.jpg', $item->cover_path);
        $this->assertSame('dvd', $item->physical_format);
        $this->assertFalse(Storage::disk('public')->exists('covers/original-cover.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($item->cover_path));
    }

    public function test_admin_can_remove_an_existing_cover(): void
    {
        Storage::disk('public')->put('covers/remove-cover.jpg', 'old-cover');

        $item = Item::factory()->film()->create([
            'title' => 'Remove cover',
            'physical_format' => 'dvd',
            'cover_path' => 'covers/remove-cover.jpg',
        ]);

        $this->actingAs(User::query()->first())
            ->put(route('admin.collection.update', $item), [
                'type' => 'film',
                'title' => 'Remove cover',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'remove_cover' => '1',
            ])
            ->assertRedirect(route('admin.collection.index'));

        $item->refresh();

        $this->assertNull($item->cover_path);
        $this->assertFalse(Storage::disk('public')->exists('covers/remove-cover.jpg'));
    }

    public function test_collection_rejects_a_non_image_cover_upload(): void
    {
        $this->actingAs(User::query()->first())
            ->from('/admin/collection/create')
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Bad cover type',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'cover_image' => UploadedFile::fake()->create('cover.txt', 10, 'text/plain'),
            ])
            ->assertRedirect('/admin/collection/create')
            ->assertSessionHasErrors('cover_image');
    }

    public function test_collection_rejects_a_failed_cover_upload_with_a_translated_message_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $tempPath = tempnam(sys_get_temp_dir(), 'shelfvault-cover');
        File::put($tempPath, 'broken upload');

        $failedUpload = new UploadedFile($tempPath, 'cover.jpg', 'image/jpeg', UPLOAD_ERR_CANT_WRITE, true);

        $this->actingAs(User::query()->first())
            ->from('/admin/collection/create')
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Cover upload failure',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'cover_image' => $failedUpload,
            ])
            ->assertRedirect('/admin/collection/create')
            ->assertSessionHasErrors('cover_image')
            ->assertSessionHasErrors(['cover_image' => __('admin.collection.validation.uploaded', ['attribute' => __('admin.collection.fields.cover_image')])]);

        File::delete($tempPath);
    }

    public function test_collection_rejects_an_overly_large_cover_upload(): void
    {
        $this->actingAs(User::query()->first())
            ->from('/admin/collection/create')
            ->post('/admin/collection', [
                'type' => 'film',
                'title' => 'Large cover',
                'status' => 'owned',
                'condition' => 'good',
                'physical_format' => 'dvd',
                'cover_image' => UploadedFile::fake()->image('cover.jpg')->size(4097),
            ])
            ->assertRedirect('/admin/collection/create')
            ->assertSessionHasErrors('cover_image');
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

    public function test_admin_can_filter_by_type(): void
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

    }

    public function test_admin_can_view_board_game_specific_fields_on_detail_page(): void
    {
        $item = Item::factory()->boardGame()->create([
            'title' => 'Catan',
            'min_players' => 3,
            'max_players' => 4,
            'play_time_minutes' => 90,
            'designer' => 'Klaus Teuber',
            'publisher' => 'Kosmos',
            'genres' => ['Strategy', 'Family'],
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('4')
            ->assertSee('90')
            ->assertSee('Klaus Teuber')
            ->assertSee('Kosmos')
            ->assertSee('Strategy, Family');
    }

    public function test_admin_can_view_tv_series_specific_fields_on_detail_page(): void
    {
        $item = Item::factory()->tvSeries()->create([
            'title' => 'The Expanse',
            'season_count' => 6,
            'episode_count' => 62,
            'runtime_minutes' => 45,
            'showrunner' => 'Naren Shankar',
            'network' => 'Syfy / Prime Video',
            'studio' => 'Alcon Television Group',
            'genres' => ['Science fiction', 'Drama'],
            'cast_members' => ['Steven Strait', 'Shohreh Aghdashloo'],
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee(__('admin.collection.types.tv_series'))
            ->assertSee(__('admin.collection.detail.sections.tv_series_overview'))
            ->assertSee('6')
            ->assertSee('62')
            ->assertSee('45 min')
            ->assertSee('Naren Shankar')
            ->assertSee('Syfy / Prime Video')
            ->assertSee('Alcon Television Group')
            ->assertSee('Science fiction, Drama');
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

    public function test_collection_form_does_not_expose_a_manual_status_choice(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertDontSee('name="status"', false);
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

    public function test_collection_detail_page_is_translated_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $item = Item::factory()->film()->create([
            'title' => 'Film français',
            'physical_format' => 'dvd',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.show', $item))
            ->assertOk()
            ->assertSee('Détail de l’objet')
            ->assertSee('Informations principales')
            ->assertSee('Détails physiques')
            ->assertSee('Métadonnées')
            ->assertSee('Retour à la collection');
    }

    public function test_collection_pages_are_translated_in_french(): void
    {
        app()->setLocale('fr');
        User::query()->first()->forceFill(['preferred_locale' => 'fr'])->save();

        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee('Ajouter un objet')
            ->assertSee('Annuler')
            ->assertSee('Choisissez un type')
            ->assertSee('Jaquette')
            ->assertSee('JPG, PNG ou WEBP — max. 4 Mo.')
            ->assertSee('Recherche de métadonnées')
            ->assertSee('Recherchez par titre pour les films et les jeux vidéo.')
            ->assertSee('Champs physiques à compléter manuellement.')
            ->assertSee('Rechercher')
            ->assertDontSee('Rechercher sur TMDb')
            ->assertDontSee('Rechercher par code-barres')
            ->assertDontSee('Scanner un code-barres')
            ->assertDontSee('Saisie manuelle disponible')
            ->assertDontSee('Placez le code-barres dans le cadre.')
            ->assertDontSee('Supprime la jaquette stockée et vide le champ de jaquette.')
            ->assertSee('Séparez les valeurs par des virgules')
            ->assertDontSee('AJOUTER UN OBJET')
            ->assertDontSee('Wishlist')
            ->assertDontSee('wanted')
            ->assertDontSee('Wanted');
    }

    public function test_collection_create_page_exposes_primary_actions_at_the_top(): void
    {
        $this->actingAs(User::query()->first())
            ->get('/admin/collection/create')
            ->assertOk()
            ->assertSee('Add item')
            ->assertSee('Cancel')
            ->assertSee('Choose a type')
            ->assertSee('Cover')
            ->assertSee('JPG, PNG or WEBP - max. 4 MB.')
            ->assertSee('Metadata search')
            ->assertSee('Search by title for films and video games.')
            ->assertSee('Physical details to complete manually.')
            ->assertSee('Search')
            ->assertDontSee('Search on TMDb')
            ->assertDontSee('Search by barcode')
            ->assertDontSee('Manual entry available')
            ->assertDontSee('Delete the stored cover and clear the cover field.')
            ->assertDontSee('Wishlist')
            ->assertDontSee('wanted')
            ->assertDontSee('ADD ITEM');
    }

    public function test_collection_edit_page_exposes_primary_actions_at_the_top(): void
    {
        $item = Item::factory()->film()->create(['title' => 'Edit actions']);

        $this->actingAs(User::query()->first())
            ->get(route('admin.collection.edit', $item))
            ->assertOk()
            ->assertSee('Edit item')
            ->assertSee('Save changes')
            ->assertSee('Cancel')
            ->assertSee('Film')
            ->assertSee('Remove cover')
            ->assertSee('Metadata search')
            ->assertSee('Search by title for films and video games.')
            ->assertSee('Search')
            ->assertDontSee('Search on TMDb')
            ->assertDontSee('Search by barcode')
            ->assertDontSee('Wishlist')
            ->assertDontSee('wanted')
            ->assertDontSee('EDIT ITEM');
    }
}
