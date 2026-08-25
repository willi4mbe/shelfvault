<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemLoan;
use App\Models\User;
use App\Services\Library\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicLibraryTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-public.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-public.lock');

        File::delete($this->defaultLockPath);
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->databasePath);
        File::delete($this->lockPath);
        File::put($this->databasePath, '');
        File::put($this->lockPath, now()->toIso8601String());

        config([
            'app.locale' => 'en',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'session.driver' => 'file',
            'session.files' => storage_path('framework/testing/sessions'),
            'shelfvault.installer.lock_path' => $this->lockPath,
        ]);
        app()->setLocale('en');

        File::ensureDirectoryExists(config('session.files'));

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_public_home_uses_library_name_enabled_types_stats_and_recent_items(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setLibraryName('William Media Library');
        $settings->setEnabledTypes(['film', 'video_game']);

        $film = Item::factory()->film()->create(['title' => 'Arrival']);
        $game = Item::factory()->videoGame()->create(['title' => 'Hollow Knight']);
        $boardGame = Item::factory()->boardGame()->create(['title' => 'Azul']);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertSee('William Media Library')
            ->assertSee(__('library.navigation.film'))
            ->assertSee(__('library.navigation.video_game'))
            ->assertSee(__('library.sections.stats'))
            ->assertSee(__('library.sections.recent'))
            ->assertSee($film->title)
            ->assertSee($game->title)
            ->assertDontSee($boardGame->title)
            ->assertSee(route('library.type', 'film'), false)
            ->assertSee(route('library.type', 'video_game'), false)
            ->assertDontSee(route('library.type', 'board_game'), false);
    }

    public function test_public_home_limits_recent_items_to_six_newest_items(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film']);

        foreach (range(1, 7) as $day) {
            Item::factory()->film()->create([
                'title' => 'Recent Film '.$day,
                'created_at' => now()->subDays($day),
            ]);
        }

        $this->get(route('library.home'))
            ->assertOk()
            ->assertViewHas('recentItems', function ($items): bool {
                return $items->count() === 6
                    && $items->pluck('title')->all() === [
                        'Recent Film 1',
                        'Recent Film 2',
                        'Recent Film 3',
                        'Recent Film 4',
                        'Recent Film 5',
                        'Recent Film 6',
                    ];
            })
            ->assertSee('Recent Film 1')
            ->assertSee('Recent Film 6')
            ->assertDontSee('Recent Film 7');
    }

    public function test_public_home_limits_active_loans_to_six_oldest_loans_first(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setEnabledTypes(['film']);
        $settings->setLoansEnabled(true);

        foreach (range(1, 6) as $day) {
            Item::factory()->film()->create([
                'title' => 'Fresh Film '.$day,
                'created_at' => now()->subDays($day),
            ]);
        }

        foreach (range(1, 7) as $day) {
            $item = Item::factory()->film()->loaned()->create([
                'title' => 'Loaned Film '.$day,
                'created_at' => now()->subDays(30 + $day),
            ]);

            ItemLoan::factory()->active()->for($item)->create([
                'borrower_name' => 'Borrower '.$day,
                'loaned_at' => now()->subDays(20 - $day),
            ]);
        }

        $this->get(route('library.home'))
            ->assertOk()
            ->assertViewHas('activeLoanItems', function ($loans): bool {
                return $loans->count() === 6
                    && $loans->pluck('item.title')->all() === [
                        'Loaned Film 1',
                        'Loaned Film 2',
                        'Loaned Film 3',
                        'Loaned Film 4',
                        'Loaned Film 5',
                        'Loaned Film 6',
                    ];
            })
            ->assertSeeInOrder([
                'Loaned Film 1',
                'Loaned Film 2',
                'Loaned Film 3',
                'Loaned Film 4',
                'Loaned Film 5',
                'Loaned Film 6',
            ])
            ->assertSee(__('library.detail.loaned_since_short'))
            ->assertDontSee('Loaned Film 7')
            ->assertDontSee('Borrower 7');
    }

    public function test_public_home_hides_active_loans_section_when_disabled_or_empty(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setEnabledTypes(['film']);
        $settings->setLoansEnabled(false);

        $item = Item::factory()->film()->loaned()->create(['title' => 'Hidden Loan']);
        ItemLoan::factory()->active()->for($item)->create(['borrower_name' => 'Hidden Borrower']);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertViewHas('activeLoanItems', fn ($loans): bool => $loans->isEmpty())
            ->assertDontSee(__('library.sections.loans'))
            ->assertDontSee('Hidden Borrower');

        $settings->setLoansEnabled(true);
        $item->itemLoans()->update(['returned_at' => now()]);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertViewHas('activeLoanItems', fn ($loans): bool => $loans->isEmpty())
            ->assertDontSee(__('library.sections.loans'))
            ->assertDontSee('Hidden Borrower');
    }

    public function test_public_home_renders_configured_accent_color(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setAccentColor('red');

        $this->get(route('library.home'))
            ->assertOk()
            ->assertSee('library-accent-red', false)
            ->assertSee('--library-accent: 239 68 68', false);
    }

    public function test_public_visibility_allows_guest_library_access_by_default(): void
    {
        $this->assertSame('public', app(LibrarySettings::class)->visibility());

        $this->get(route('library.home'))->assertOk();
        $this->get(route('library.favorites'))->assertOk();
    }

    public function test_private_visibility_redirects_guests_to_login_and_returns_after_authentication(): void
    {
        app(LibrarySettings::class)->setVisibility('private');

        $item = Item::factory()->film()->create(['title' => 'Private Item']);
        $admin = User::query()->create([
            'name' => 'Admin',
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
            'preferred_locale' => 'en',
        ]);

        $target = route('library.items.show', $item);

        $this->get($target)
            ->assertRedirect(route('login'));

        $this->post(route('admin.login'), [
            'identifier' => $admin->email,
            'password' => 'password',
        ])->assertRedirect($target);

        $this->get($target)
            ->assertOk()
            ->assertSee('Private Item');
    }

    public function test_private_visibility_keeps_admin_routes_protected_for_guests(): void
    {
        app(LibrarySettings::class)->setVisibility('private');

        $this->get(route('admin'))
            ->assertRedirect(route('login'));
    }

    public function test_disabled_type_listing_and_detail_are_not_publicly_available(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film']);

        $film = Item::factory()->film()->create(['title' => 'Dune']);
        $boardGame = Item::factory()->boardGame()->create(['title' => 'Cascadia']);

        $this->get(route('library.type', 'film'))
            ->assertOk()
            ->assertSee($film->title)
            ->assertDontSee($boardGame->title);

        $this->get(route('library.type', 'board_game'))->assertNotFound();
        $this->get(route('library.items.show', $boardGame))->assertNotFound();
    }

    public function test_loans_follow_public_library_setting(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setEnabledTypes(['film']);
        $settings->setLoansEnabled(false);

        $item = Item::factory()->film()->loaned()->create(['title' => 'Blade Runner']);
        ItemLoan::factory()->active()->for($item)->create(['borrower_name' => 'Alex']);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertDontSee(route('library.loans'), false)
            ->assertDontSee(__('library.sections.loans'));

        $this->get(route('library.loans'))->assertNotFound();

        $settings->setLoansEnabled(true);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertSee(route('library.loans'), false)
            ->assertSee(__('library.stats.loaned'))
            ->assertSee(__('library.sections.loans'))
            ->assertSee('Alex');

        $this->get(route('library.loans'))
            ->assertOk()
            ->assertSee('Blade Runner')
            ->assertSee('Alex');
    }

    public function test_public_item_detail_uses_real_item_data(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['tv_series']);

        $item = Item::factory()->tvSeries()->create([
            'title' => 'The Expanse',
            'description' => 'A crew uncovers a system-wide conspiracy.',
            'season_count' => 6,
            'episode_count' => 62,
        ]);

        $this->get(route('library.items.show', $item))
            ->assertOk()
            ->assertSee('The Expanse')
            ->assertSee('A crew uncovers a system-wide conspiracy.')
            ->assertSee(__('library.types.tv_series'))
            ->assertSee(trans_choice('library.detail.seasons', 6, ['count' => 6]))
            ->assertSee(trans_choice('library.detail.episodes', 62, ['count' => 62]));
    }

    public function test_public_item_detail_lists_physical_main_metadata_cast_and_notes(): void
    {
        app()->setLocale('fr');
        app(LibrarySettings::class)->setEnabledTypes(['film']);

        $item = Item::factory()->film()->loaned()->create([
            'title' => 'Furiosa — Une saga Mad Max',
            'original_title' => 'Furiosa: A Mad Max Saga',
            'release_year' => 2024,
            'barcode' => '31245632456',
            'condition' => 'new',
            'physical_format' => 'blu_ray',
            'edition' => 'normal',
            'region' => 'test',
            'location' => 'maison',
            'is_favorite' => true,
            'acquired_at' => '2026-04-08',
            'cast_members' => ['Anya Taylor-Joy', 'Chris Hemsworth', 'Tom Burke'],
            'studio' => 'Warner Bros. Pictures',
            'external_tmdb_id' => '786892',
            'personal_notes' => 'prefere films SF',
        ]);

        $this->get(route('library.items.show', $item))
            ->assertOk()
            ->assertSee(__('library.detail.cast'))
            ->assertSee('Anya Taylor-Joy')
            ->assertSee('Chris Hemsworth')
            ->assertSee(__('library.detail.main_information'))
            ->assertSee('Furiosa: A Mad Max Saga')
            ->assertSee('31245632456')
            ->assertSee(__('library.values.yes'))
            ->assertSee(__('library.detail.physical_details'))
            ->assertSeeInOrder([__('library.detail.physical_details'), __('library.detail.cast')])
            ->assertSee('Blu-ray')
            ->assertSee('normal')
            ->assertSee('test')
            ->assertSee('maison')
            ->assertSee('2026-04-08')
            ->assertSee(__('library.detail.metadata'))
            ->assertSee('Warner Bros. Pictures')
            ->assertSee('786892')
            ->assertSee(__('library.detail.notes'))
            ->assertSee('prefere films SF');
    }

    public function test_public_favorites_page_only_lists_favorite_enabled_items(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film', 'video_game']);

        $favorite = Item::factory()->film()->create([
            'title' => 'Favorite Film',
            'is_favorite' => true,
        ]);
        $regular = Item::factory()->film()->create([
            'title' => 'Regular Film',
            'is_favorite' => false,
        ]);
        $disabledFavorite = Item::factory()->boardGame()->create([
            'title' => 'Hidden Favorite',
            'is_favorite' => true,
        ]);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertSee(route('library.favorites'), false);

        $this->get(route('library.favorites'))
            ->assertOk()
            ->assertSee(__('library.navigation.favorites'))
            ->assertSee($favorite->title)
            ->assertDontSee($regular->title)
            ->assertDontSee($disabledFavorite->title);
    }

    public function test_public_filters_can_limit_by_type_favorite_loan_genre_and_year(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setEnabledTypes(['film', 'video_game']);
        $settings->setLoansEnabled(true);

        $matching = Item::factory()->videoGame()->loaned()->create([
            'title' => 'Filtered Match',
            'is_favorite' => true,
            'release_year' => 2021,
            'genres' => ['rpg'],
        ]);
        $wrongType = Item::factory()->film()->loaned()->create([
            'title' => 'Wrong Type',
            'is_favorite' => true,
            'release_year' => 2021,
            'genres' => ['rpg'],
        ]);
        $wrongGenre = Item::factory()->videoGame()->loaned()->create([
            'title' => 'Wrong Genre',
            'is_favorite' => true,
            'release_year' => 2021,
            'genres' => ['adventure'],
        ]);

        ItemLoan::factory()->active()->for($matching)->create();
        ItemLoan::factory()->active()->for($wrongType)->create();
        ItemLoan::factory()->active()->for($wrongGenre)->create();

        $this->get(route('library.recent', [
            'type' => 'video_game',
            'favorite' => '1',
            'availability' => 'loaned',
            'genre' => 'rpg',
            'year' => 2021,
        ]))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($wrongType->title)
            ->assertDontSee($wrongGenre->title);
    }

    public function test_public_listing_sort_controls_change_item_order(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film']);

        Item::factory()->film()->create([
            'title' => 'Zulu',
            'sort_title' => 'Zulu',
            'release_year' => 1984,
            'created_at' => now()->subDays(3),
        ]);
        Item::factory()->film()->create([
            'title' => 'Alpha',
            'sort_title' => 'Alpha',
            'release_year' => 2020,
            'created_at' => now()->subDay(),
        ]);

        $this->get(route('library.type', ['type' => 'film', 'sort' => 'title']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha', 'Zulu']);

        $this->get(route('library.type', ['type' => 'film', 'sort' => 'year']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha', 'Zulu']);
    }

    public function test_public_search_stays_within_the_source_section_and_empty_search_returns_to_it(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film', 'board_game']);

        Item::factory()->film()->create([
            'title' => '2 Fast 2 Furious',
            'created_at' => now()->subDay(),
        ]);
        Item::factory()->film()->create([
            'title' => 'Furiosa',
            'created_at' => now(),
        ]);
        Item::factory()->film()->create([
            'title' => 'Super Mario Galaxy, le film',
            'created_at' => now()->subDays(2),
        ]);
        Item::factory()->film()->create([
            'title' => 'Un Indien dans la ville',
            'created_at' => now()->subDays(3),
        ]);
        Item::factory()->boardGame()->create([
            'title' => 'Fast Board Game',
            'created_at' => now()->subDays(4),
        ]);

        $this->get(route('library.search', ['q' => 'fast', 'from' => 'film']))
            ->assertOk()
            ->assertSee('2 Fast 2 Furious')
            ->assertDontSee('Fast Board Game')
            ->assertDontSee('Furiosa')
            ->assertDontSee('Super Mario Galaxy, le film')
            ->assertSee('type="search"', false);

        $this->get(route('library.search', ['q' => 'Furiosa', 'from' => 'film']))
            ->assertOk()
            ->assertSee('Furiosa')
            ->assertDontSee('2 Fast 2 Furious');

        $this->get(route('library.search', ['q' => '']))
            ->assertRedirect(route('library.recent'));

        $this->get(route('library.search', ['q' => '', 'from' => 'film']))
            ->assertRedirect(route('library.type', 'film'));
    }

    public function test_public_loans_use_active_loan_records_for_badges_and_availability(): void
    {
        $settings = app(LibrarySettings::class);
        $settings->setEnabledTypes(['film', 'board_game']);
        $settings->setLoansEnabled(true);

        $loanedBoardGame = Item::factory()->boardGame()->owned()->create([
            'title' => 'Catan',
        ]);
        ItemLoan::factory()->active()->for($loanedBoardGame)->create([
            'borrower_name' => 'Max',
        ]);
        $availableFilm = Item::factory()->film()->owned()->create([
            'title' => 'The Matrix',
        ]);

        $this->get(route('library.type', 'board_game'))
            ->assertOk()
            ->assertSee('Catan')
            ->assertSee(__('library.badges.loaned'));

        $this->get(route('library.recent', ['availability' => 'loaned']))
            ->assertOk()
            ->assertSee('Catan')
            ->assertDontSee('The Matrix');

        $this->get(route('library.recent', ['availability' => 'available']))
            ->assertOk()
            ->assertSee('The Matrix')
            ->assertDontSee('Catan');
    }

    public function test_public_collection_routes_use_real_data_without_sidebar_genre_year_links(): void
    {
        app(LibrarySettings::class)->setEnabledTypes(['film', 'board_game']);

        $old = Item::factory()->film()->create([
            'title' => 'Older Film',
            'release_year' => 1984,
            'genres' => ['drama'],
            'created_at' => now()->subDays(10),
        ]);
        $recent = Item::factory()->boardGame()->create([
            'title' => 'Recent Strategy',
            'release_year' => 2024,
            'genres' => ['strategy'],
            'created_at' => now(),
        ]);

        $this->get(route('library.home'))
            ->assertOk()
            ->assertSee(route('library.recent'), false)
            ->assertDontSee('<span>'.__('library.navigation.recent').'</span>', false)
            ->assertDontSee(route('library.genres'), false)
            ->assertDontSee(route('library.years'), false);

        $this->get(route('library.recent'))
            ->assertOk()
            ->assertSeeInOrder([$recent->title, $old->title]);

        $this->get(route('library.genres', ['genre' => 'strategy']))
            ->assertOk()
            ->assertSee($recent->title)
            ->assertDontSee($old->title);

        $this->get(route('library.years', ['year' => 1984]))
            ->assertOk()
            ->assertSee($old->title)
            ->assertDontSee($recent->title);
    }

    public function test_public_item_detail_uses_translated_condition_status_and_format(): void
    {
        app()->setLocale('fr');
        app(LibrarySettings::class)->setEnabledTypes(['film']);

        $item = Item::factory()->film()->create([
            'title' => 'Film traduit',
            'physical_format' => 'blu_ray',
            'condition' => 'very_good',
            'status' => 'owned',
        ]);

        $this->get(route('library.items.show', $item))
            ->assertOk()
            ->assertSee('Blu-ray')
            ->assertSee('Très bon')
            ->assertDontSee('Possédé')
            ->assertDontSee('Very Good');
    }
}
