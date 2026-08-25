<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Metadata\MetadataImportMapper;
use App\Services\Metadata\Providers\LocalItemBarcodeLookupProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeTextTranslationProvider;
use Tests\TestCase;

class AdminMetadataLookupTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-metadata.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-metadata.lock');

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
            'barcode.providers' => [],
            'services.tmdb.api_key' => '',
            'services.tmdb.bearer_token' => '',
            'services.igdb.client_id' => '',
            'services.igdb.client_secret' => '',
            'services.igdb.access_token' => '',
            'services.bgg.token' => '',
            'services.translation.provider' => '',
            'services.translation.source_locale' => 'en',
            'services.translation.google.api_key' => '',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
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

    public function test_tmdb_search_returns_a_clear_message_when_tmdb_is_not_configured(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'The Matrix',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.tmdb_not_configured'));
    }

    public function test_title_search_requires_a_type_before_searching(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'title' => 'The Matrix',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.collection.lookup.choose_type_before_searching'));
    }

    public function test_title_search_requires_a_title_before_searching(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.collection.lookup.enter_title_to_search'));
    }

    public function test_board_game_search_returns_a_clear_message_when_bgg_is_not_configured(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Catan',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.bgg_not_configured'));
    }

    public function test_igdb_search_returns_a_clear_message_when_igdb_is_not_configured(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'video_game',
                'title' => 'Super Mario Galaxy',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.igdb_not_configured'));
    }

    public function test_tmdb_search_returns_normalized_candidates_when_tmdb_is_configured(): void
    {
        config(['services.tmdb.api_key' => 'test-key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    [
                        'id' => 603,
                        'title' => 'The Matrix',
                        'original_title' => 'The Matrix',
                        'release_date' => '1999-03-31',
                        'overview' => 'A hacker discovers the truth.',
                        'poster_path' => '/matrix.jpg',
                        'vote_average' => 8.7,
                    ],
                    [
                        'id' => 604,
                        'title' => 'The Matrix Reloaded',
                        'original_title' => 'The Matrix Reloaded',
                        'release_date' => '2003-05-15',
                        'overview' => 'The story continues.',
                        'poster_path' => '/matrix-reloaded.jpg',
                        'vote_average' => 7.0,
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
                'release_year' => 1999,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('message', __('admin.collection.metadata.results_found'))
            ->assertJsonPath('data.candidates.0.tmdb_id', 603)
            ->assertJsonPath('data.candidates.0.title', 'The Matrix')
            ->assertJsonPath('data.candidates.0.release_year', 1999)
            ->assertJsonPath('data.candidates.0.poster_url', 'https://image.tmdb.org/t/p/w500/matrix.jpg');
    }

    public function test_tmdb_search_uses_database_settings_before_config_fallback(): void
    {
        config(['services.tmdb.api_key' => 'fallback-key']);

        app(ExternalServiceSettings::class)->set('tmdb', 'api_key', 'db-tmdb-key', true);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    [
                        'id' => 603,
                        'title' => 'The Matrix',
                        'original_title' => 'The Matrix',
                        'release_date' => '1999-03-31',
                        'overview' => 'A hacker discovers the truth.',
                        'poster_path' => '/matrix.jpg',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found');

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://api.themoviedb.org/3/search/movie')
                && str_contains($request->url(), 'api_key=db-tmdb-key')
                && ! str_contains($request->url(), 'api_key=fallback-key');
        });
    }

    public function test_tmdb_tv_search_returns_normalized_candidates_when_tmdb_is_configured(): void
    {
        config(['services.tmdb.api_key' => 'test-key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/tv*' => Http::response([
                'results' => [
                    [
                        'id' => 63639,
                        'name' => 'The Expanse',
                        'original_name' => 'The Expanse',
                        'first_air_date' => '2015-12-14',
                        'overview' => 'A missing woman links a detective, a ship captain, and a conspiracy.',
                        'poster_path' => '/expanse.jpg',
                        'vote_average' => 8.1,
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'tv_series',
                'title' => 'The Expanse',
                'release_year' => 2015,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'tmdb')
            ->assertJsonPath('data.candidates.0.source', 'tmdb')
            ->assertJsonPath('data.candidates.0.type', 'tv_series')
            ->assertJsonPath('data.candidates.0.tmdb_id', 63639)
            ->assertJsonPath('data.candidates.0.title', 'The Expanse')
            ->assertJsonPath('data.candidates.0.original_title', 'The Expanse')
            ->assertJsonPath('data.candidates.0.release_year', 2015)
            ->assertJsonPath('data.candidates.0.poster_url', 'https://image.tmdb.org/t/p/w500/expanse.jpg');

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://api.themoviedb.org/3/search/tv')
                && str_contains($request->url(), 'first_air_date_year=2015');
        });
    }

    public function test_tmdb_search_uses_the_configured_bearer_token(): void
    {
        config([
            'services.tmdb.api_key' => '',
            'services.tmdb.bearer_token' => 'bearer-test-token',
        ]);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    [
                        'id' => 603,
                        'title' => 'The Matrix',
                        'original_title' => 'The Matrix',
                        'release_date' => '1999-03-31',
                        'overview' => 'A hacker discovers the truth.',
                        'poster_path' => '/matrix.jpg',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found');

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer bearer-test-token');
        });
    }

    public function test_igdb_search_returns_normalized_video_game_candidates_when_configured(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
        ]);

        Http::fake([
            'https://api.igdb.com/v4/games' => Http::response([
                [
                    'id' => 1234,
                    'name' => 'Super Mario Galaxy',
                    'summary' => 'Mario explores space.',
                    'first_release_date' => 1194393600,
                    'cover' => [
                        'url' => '//images.igdb.com/igdb/image/upload/t_thumb/co1234.jpg',
                    ],
                    'platforms' => [
                        ['name' => 'Wii'],
                    ],
                    'involved_companies' => [
                        [
                            'developer' => true,
                            'publisher' => false,
                            'company' => ['name' => 'Nintendo EAD Tokyo'],
                        ],
                        [
                            'developer' => false,
                            'publisher' => true,
                            'company' => ['name' => 'Nintendo'],
                        ],
                    ],
                    'genres' => [
                        ['name' => 'Platform'],
                        ['name' => 'Adventure'],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'video_game',
                'title' => 'Super Mario Galaxy',
                'release_year' => 2007,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'igdb')
            ->assertJsonPath('data.candidates.0.source', 'igdb')
            ->assertJsonPath('data.candidates.0.igdb_id', 1234)
            ->assertJsonPath('data.candidates.0.title', 'Super Mario Galaxy')
            ->assertJsonPath('data.candidates.0.release_year', 2007)
            ->assertJsonPath('data.candidates.0.platforms', ['Wii'])
            ->assertJsonPath('data.candidates.0.developer', 'Nintendo EAD Tokyo')
            ->assertJsonPath('data.candidates.0.publisher', 'Nintendo')
            ->assertJsonPath('data.candidates.0.genres', ['Platform', 'Adventure'])
            ->assertJsonPath('data.candidates.0.poster_url', 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1234.jpg');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.igdb.com/v4/games'
                && $request->hasHeader('Client-ID', 'igdb-client')
                && $request->hasHeader('Authorization', 'Bearer igdb-token')
                && str_contains($request->body(), 'search "Super Mario Galaxy";')
                && str_contains($request->body(), 'where first_release_date >=');
        });
    }

    public function test_bgg_search_returns_normalized_board_game_candidates_when_configured(): void
    {
        config(['services.bgg.token' => 'bgg-test-token']);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/search*' => Http::response($this->bggSearchPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Catan',
                'release_year' => 1995,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'bgg')
            ->assertJsonPath('data.candidates.0.source', 'bgg')
            ->assertJsonPath('data.candidates.0.type', 'board_game')
            ->assertJsonPath('data.candidates.0.bgg_id', 13)
            ->assertJsonPath('data.candidates.0.title', 'CATAN')
            ->assertJsonPath('data.candidates.0.release_year', 1995)
            ->assertJsonPath('data.candidates.0.poster_url', 'https://cf.geekdo-images.com/catan.jpg')
            ->assertJsonPath('data.candidates.0.min_players', 3)
            ->assertJsonPath('data.candidates.0.max_players', 4)
            ->assertJsonPath('data.candidates.0.play_time_minutes', 120)
            ->assertJsonPath('data.candidates.0.age_rating', '10+')
            ->assertJsonPath('data.candidates.0.designer', 'Klaus Teuber')
            ->assertJsonPath('data.candidates.0.publisher', 'Kosmos, Catan Studio')
            ->assertJsonPath('data.candidates.0.categories', ['Negotiation', 'Economic'])
            ->assertJsonPath('data.candidates.0.mechanisms', ['Dice Rolling', 'Trading']);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://boardgamegeek.com/xmlapi2/search')
                && $request->hasHeader('Authorization', 'Bearer bgg-test-token');
        });
    }

    public function test_bgg_search_ranks_exact_monopoly_before_variants(): void
    {
        config(['services.bgg.token' => 'bgg-test-token']);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/search*' => Http::response($this->bggMonopolySearchPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggMonopolyThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Monopoly',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'bgg')
            ->assertJsonPath('data.candidates.0.bgg_id', 1406)
            ->assertJsonPath('data.candidates.0.title', 'Monopoly');

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://boardgamegeek.com/xmlapi2/search')
                && str_contains($request->url(), 'type=boardgame')
                && ! str_contains($request->url(), 'boardgameexpansion');
        });

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://boardgamegeek.com/xmlapi2/thing')
                && str_contains($request->url(), 'id=1406%2C13186%2C3689%2C1932')
                && str_contains($request->url(), 'stats=1');
        });
    }

    public function test_tmdb_search_paginates_in_repeatable_ten_result_slices(): void
    {
        config(['services.tmdb.api_key' => 'test-key']);

        Http::fake([
            'https://api.themoviedb.org/3/search/movie*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $apiPage = (int) ($query['page'] ?? 1);
                $start = $apiPage === 1 ? 1 : 21;
                $count = $apiPage === 1 ? 20 : 5;

                return Http::response([
                    'page' => $apiPage,
                    'total_pages' => 2,
                    'results' => $this->tmdbSearchResults($start, $count),
                ], 200);
            },
        ]);

        $first = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
                'page' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.tmdb_id', 1)
            ->assertJsonPath('data.candidates.9.tmdb_id', 10)
            ->assertJsonPath('data.pagination.has_more', true)
            ->assertJsonPath('data.pagination.next_page', 2);

        $this->assertCount(10, $first->json('data.candidates'));

        $second = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
                'page' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.tmdb_id', 11)
            ->assertJsonPath('data.candidates.9.tmdb_id', 20)
            ->assertJsonPath('data.pagination.has_more', true)
            ->assertJsonPath('data.pagination.next_page', 3);

        $this->assertCount(10, $second->json('data.candidates'));

        $third = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'film',
                'title' => 'Matrix',
                'page' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.tmdb_id', 21)
            ->assertJsonPath('data.pagination.has_more', false)
            ->assertJsonPath('data.pagination.next_page', null);

        $this->assertCount(5, $third->json('data.candidates'));
    }

    public function test_igdb_search_paginates_with_offset_and_reports_when_finished(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
        ]);

        Http::fake([
            'https://api.igdb.com/v4/games' => function ($request) {
                $secondPage = str_contains($request->body(), 'offset 10;');
                $games = [];
                $start = $secondPage ? 11 : 1;
                $count = $secondPage ? 3 : 11;

                for ($id = $start; $id < $start + $count; $id++) {
                    $games[] = [
                        'id' => $id,
                        'name' => 'Game '.$id,
                        'first_release_date' => 1194393600,
                    ];
                }

                return Http::response($games, 200);
            },
        ]);

        $first = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'video_game',
                'title' => 'Game',
                'page' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.igdb_id', 1)
            ->assertJsonPath('data.candidates.9.igdb_id', 10)
            ->assertJsonPath('data.pagination.has_more', true)
            ->assertJsonPath('data.pagination.next_page', 2);

        $this->assertCount(10, $first->json('data.candidates'));

        $second = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'video_game',
                'title' => 'Game',
                'page' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.igdb_id', 11)
            ->assertJsonPath('data.pagination.has_more', false)
            ->assertJsonPath('data.pagination.next_page', null);

        $this->assertCount(3, $second->json('data.candidates'));

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'limit 11; offset 10;'));
    }

    public function test_bgg_search_reveals_ranked_results_by_twelve_result_slices(): void
    {
        config(['services.bgg.token' => 'bgg-test-token']);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/search*' => Http::response($this->bggManySearchPayload(14), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://boardgamegeek.com/xmlapi2/thing*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $ids = array_map('intval', explode(',', (string) ($query['id'] ?? '')));

                return Http::response($this->bggManyThingPayload($ids), 200, [
                    'Content-Type' => 'application/xml',
                ]);
            },
        ]);

        $first = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Game',
                'page' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.bgg_id', 1)
            ->assertJsonPath('data.candidates.11.bgg_id', 12)
            ->assertJsonPath('data.pagination.has_more', true)
            ->assertJsonPath('data.pagination.next_page', 2);

        $this->assertCount(12, $first->json('data.candidates'));

        $second = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Game',
                'page' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.candidates.0.bgg_id', 13)
            ->assertJsonPath('data.pagination.has_more', false)
            ->assertJsonPath('data.pagination.next_page', null);

        $this->assertCount(2, $second->json('data.candidates'));
    }

    public function test_bgg_import_maps_board_game_metadata_and_imports_a_cover_locally(): void
    {
        config(['services.bgg.token' => 'bgg-test-token']);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://cf.geekdo-images.com/catan.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'bgg',
                'bgg_id' => 13,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'bgg')
            ->assertJsonPath('data.type', 'board_game')
            ->assertJsonPath('data.title', 'CATAN')
            ->assertJsonPath('data.description', 'Trade, build, and settle.')
            ->assertJsonPath('data.release_year', 1995)
            ->assertJsonPath('data.min_players', 3)
            ->assertJsonPath('data.max_players', 4)
            ->assertJsonPath('data.play_time_minutes', 120)
            ->assertJsonPath('data.age_rating', '10+')
            ->assertJsonPath('data.designer', 'Klaus Teuber')
            ->assertJsonPath('data.publisher', 'Kosmos, Catan Studio')
            ->assertJsonPath('data.genres', ['Negotiation', 'Economic', 'Dice Rolling', 'Trading'])
            ->assertJsonMissingPath('data.bgg_id')
            ->assertJsonMissingPath('data.poster_url');

        $coverPath = $response->json('data.cover_path');

        $this->assertIsString($coverPath);
        $this->assertTrue(str_starts_with($coverPath, 'covers/'));
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_bgg_import_translates_description_and_genres_to_the_admin_locale(): void
    {
        config([
            'services.bgg.token' => 'bgg-test-token',
            'services.translation.provider' => 'google',
            'services.translation.google.api_key' => 'google-test-key',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://translation.googleapis.com/language/translate/v2*' => fn ($request) => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => $this->bggTranslatedTextFromRequest($request->body()),
                            'detectedSourceLanguage' => 'en',
                        ],
                    ],
                ],
            ], 200),
            'https://cf.geekdo-images.com/catan.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'bgg',
                'bgg_id' => 13,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'bgg')
            ->assertJsonPath('data.title', 'CATAN')
            ->assertJsonPath('data.description', 'Echangez, construisez et colonisez.')
            ->assertJsonPath('data.designer', 'Klaus Teuber')
            ->assertJsonPath('data.publisher', 'Kosmos, Catan Studio')
            ->assertJsonPath('data.genres', ['Negociation', 'Economique', 'Lancer de des', 'Commerce']);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2?key=google-test-key')
                && str_contains($request->body(), 'q=Trade%2C+build%2C+and+settle.')
                && str_contains($request->body(), 'target=fr')
                && ! str_contains($request->body(), 'source=');
        });
    }

    public function test_bgg_import_does_not_call_translation_when_admin_locale_is_english(): void
    {
        config([
            'services.bgg.token' => 'bgg-test-token',
            'services.translation.provider' => 'google',
            'services.translation.google.api_key' => 'google-test-key',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
        ]);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([], 500),
            'https://cf.geekdo-images.com/catan.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'bgg',
                'bgg_id' => 13,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.description', 'Trade, build, and settle.')
            ->assertJsonPath('data.genres', ['Negotiation', 'Economic', 'Dice Rolling', 'Trading']);

        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2'));
    }

    public function test_bgg_import_keeps_original_metadata_when_translation_fails(): void
    {
        config([
            'services.bgg.token' => 'bgg-test-token',
            'services.translation.provider' => 'google',
            'services.translation.google.api_key' => 'google-test-key',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/thing*' => Http::response($this->bggThingPayload(), 200, [
                'Content-Type' => 'application/xml',
            ]),
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([], 500),
            'https://cf.geekdo-images.com/catan.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'bgg',
                'bgg_id' => 13,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.description', 'Trade, build, and settle.')
            ->assertJsonPath('data.genres', ['Negotiation', 'Economic', 'Dice Rolling', 'Trading']);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2?key=google-test-key'));
    }

    public function test_bgg_search_reports_authorization_errors_without_exposing_the_token(): void
    {
        config(['services.bgg.token' => 'bgg-test-token']);

        Http::fake([
            'https://boardgamegeek.com/xmlapi2/search*' => Http::response('Forbidden', 403),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'board_game',
                'title' => 'Catan',
            ])
            ->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('admin.collection.metadata.bgg_forbidden'))
            ->assertDontSee('bgg-test-token');
    }

    public function test_tmdb_movie_mapping_limits_cast_members_to_the_first_five_actors(): void
    {
        $mapped = (new MetadataImportMapper())->mapTmdbMovie(
            $this->tmdbMoviePayload(),
            [
                'cast' => [
                    ['name' => 'Actor 1'],
                    ['name' => 'Actor 2'],
                    ['name' => 'Actor 3'],
                    ['name' => 'Actor 4'],
                    ['name' => 'Actor 5'],
                    ['name' => 'Actor 6'],
                ],
            ],
        );

        $this->assertSame([
            'Actor 1',
            'Actor 2',
            'Actor 3',
            'Actor 4',
            'Actor 5',
        ], $mapped['cast_members']);
    }

    public function test_tmdb_movie_mapping_reads_the_configured_region_certification_from_nested_release_dates(): void
    {
        config(['services.tmdb.region' => 'FR']);

        $mapped = (new MetadataImportMapper())->mapTmdbMovie(
            $this->tmdbMoviePayload(),
            [],
            [
                'results' => [
                    [
                        'iso_3166_1' => 'US',
                        'release_dates' => [
                            ['certification' => 'R'],
                        ],
                    ],
                    [
                        'iso_3166_1' => 'FR',
                        'release_dates' => [
                            ['certification' => ''],
                            ['certification' => '12'],
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame('12', $mapped['age_rating']);
    }

    public function test_tmdb_movie_mapping_falls_back_to_any_region_certification(): void
    {
        config(['services.tmdb.region' => 'FR']);

        $mapped = (new MetadataImportMapper())->mapTmdbMovie(
            $this->tmdbMoviePayload(),
            [],
            [
                'results' => [
                    [
                        'iso_3166_1' => 'FR',
                        'release_dates' => [
                            ['certification' => ''],
                        ],
                    ],
                    [
                        'iso_3166_1' => 'US',
                        'release_dates' => [
                            ['certification' => 'R'],
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame('R', $mapped['age_rating']);
    }

    public function test_tmdb_tv_series_mapping_reads_series_metadata_and_regional_rating(): void
    {
        config(['services.tmdb.region' => 'FR']);

        $mapped = (new MetadataImportMapper())->mapTmdbTvSeries(
            $this->tmdbTvSeriesPayload(),
            [
                'cast' => [
                    ['name' => 'Steven Strait'],
                    ['name' => 'Shohreh Aghdashloo'],
                    ['name' => 'Dominique Tipper'],
                    ['name' => 'Wes Chatham'],
                    ['name' => 'Cas Anvar'],
                    ['name' => 'Frankie Adams'],
                ],
            ],
            [
                'results' => [
                    ['iso_3166_1' => 'US', 'rating' => 'TV-14'],
                    ['iso_3166_1' => 'FR', 'rating' => '16'],
                ],
            ],
        );

        $this->assertSame('tv_series', $mapped['type']);
        $this->assertSame('The Expanse', $mapped['title']);
        $this->assertSame('The Expanse', $mapped['original_title']);
        $this->assertSame('A missing woman links a detective, a ship captain, and a conspiracy.', $mapped['description']);
        $this->assertSame(2015, $mapped['release_year']);
        $this->assertSame(2022, $mapped['end_year']);
        $this->assertSame(6, $mapped['season_count']);
        $this->assertSame(62, $mapped['episode_count']);
        $this->assertSame(45, $mapped['runtime_minutes']);
        $this->assertSame('Mark Fergus, Hawk Ostby', $mapped['showrunner']);
        $this->assertSame('Syfy, Prime Video', $mapped['network']);
        $this->assertSame(['Science Fiction', 'Drama'], $mapped['genres']);
        $this->assertSame([
            'Steven Strait',
            'Shohreh Aghdashloo',
            'Dominique Tipper',
            'Wes Chatham',
            'Cas Anvar',
        ], $mapped['cast_members']);
        $this->assertSame('16', $mapped['age_rating']);
        $this->assertSame(63639, $mapped['external_tmdb_id']);
    }

    public function test_igdb_game_mapping_reads_video_game_metadata(): void
    {
        $mapped = (new MetadataImportMapper())->mapIgdbGame($this->igdbGamePayload());

        $this->assertSame('video_game', $mapped['type']);
        $this->assertSame('Super Mario Galaxy', $mapped['title']);
        $this->assertSame('Mario explores space.', $mapped['description']);
        $this->assertSame(2007, $mapped['release_year']);
        $this->assertSame(['Platform', 'Adventure'], $mapped['genres']);
        $this->assertSame('Wii, Wii U', $mapped['platform']);
        $this->assertSame('Nintendo EAD Tokyo', $mapped['developer']);
        $this->assertSame('Nintendo', $mapped['publisher']);
        $this->assertSame(['Single player'], $mapped['modes']);
        $this->assertSame('PEGI 3', $mapped['age_rating']);
        $this->assertSame(1234, $mapped['external_igdb_id']);
    }

    public function test_tmdb_import_maps_film_metadata_and_imports_a_poster_locally(): void
    {
        config([
            'services.tmdb.api_key' => 'test-key',
            'services.tmdb.region' => 'FR',
        ]);

        Http::fake([
            'https://api.themoviedb.org/3/movie/603*' => Http::response([
                'id' => 603,
                'title' => 'The Matrix',
                'original_title' => 'The Matrix',
                'overview' => 'A computer hacker learns about the true nature of reality.',
                'release_date' => '1999-03-31',
                'runtime' => 136,
                'genres' => [
                    ['name' => 'Action'],
                    ['name' => 'Science fiction'],
                ],
                'production_companies' => [
                    ['name' => 'Village Roadshow Pictures'],
                ],
                'poster_path' => '/matrix.jpg',
                'credits' => [
                    'cast' => [
                        ['name' => 'Keanu Reeves'],
                        ['name' => 'Carrie-Anne Moss'],
                        ['name' => 'Laurence Fishburne'],
                        ['name' => 'Hugo Weaving'],
                        ['name' => 'Gloria Foster'],
                        ['name' => 'Joe Pantoliano'],
                    ],
                    'crew' => [
                        ['job' => 'Director', 'name' => 'Lana Wachowski'],
                        ['job' => 'Writer', 'name' => 'Lilly Wachowski'],
                    ],
                ],
                'release_dates' => [
                    'results' => [
                        [
                            'iso_3166_1' => 'US',
                            'release_dates' => [
                                ['certification' => 'R'],
                            ],
                        ],
                        [
                            'iso_3166_1' => 'FR',
                            'release_dates' => [
                                ['certification' => ''],
                                ['certification' => '12'],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://image.tmdb.org/t/p/w500/matrix.jpg' => Http::response('poster-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'tmdb',
                'tmdb_id' => 603,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('message', __('admin.collection.metadata.metadata_imported'))
            ->assertJsonPath('data.type', 'film')
            ->assertJsonPath('data.title', 'The Matrix')
            ->assertJsonPath('data.original_title', 'The Matrix')
            ->assertJsonPath('data.release_year', 1999)
            ->assertJsonPath('data.runtime_minutes', 136)
            ->assertJsonPath('data.studio', 'Village Roadshow Pictures')
            ->assertJsonPath('data.director', 'Lana Wachowski')
            ->assertJsonPath('data.external_tmdb_id', 603)
            ->assertJsonPath('data.age_rating', '12')
            ->assertJsonPath('data.genres', ['Action', 'Science fiction'])
            ->assertJsonPath('data.cast_members', [
                'Keanu Reeves',
                'Carrie-Anne Moss',
                'Laurence Fishburne',
                'Hugo Weaving',
                'Gloria Foster',
            ])
            ->assertJsonMissingPath('data.physical_format')
            ->assertJsonMissingPath('data.edition')
            ->assertJsonMissingPath('data.region')
            ->assertJsonMissingPath('data.barcode')
            ->assertJsonMissingPath('data.condition')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.location');

        $coverPath = $response->json('data.cover_path');

        $this->assertIsString($coverPath);
        $this->assertTrue(str_starts_with($coverPath, 'covers/'));
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_tmdb_import_keeps_metadata_when_the_poster_download_fails(): void
    {
        config(['services.tmdb.api_key' => 'test-key']);

        Http::fake([
            'https://api.themoviedb.org/3/movie/603*' => Http::response([
                'id' => 603,
                'title' => 'The Matrix',
                'original_title' => 'The Matrix',
                'overview' => 'A computer hacker learns about the true nature of reality.',
                'release_date' => '1999-03-31',
                'runtime' => 136,
                'genres' => [
                    ['name' => 'Action'],
                ],
                'production_companies' => [
                    ['name' => 'Village Roadshow Pictures'],
                ],
                'poster_path' => '/matrix.jpg',
                'credits' => [
                    'cast' => [
                        ['name' => 'Keanu Reeves'],
                    ],
                    'crew' => [
                        ['job' => 'Director', 'name' => 'Lana Wachowski'],
                    ],
                ],
                'release_dates' => [
                    'results' => [],
                ],
            ], 200),
            'https://image.tmdb.org/t/p/w500/matrix.jpg' => Http::response('', 404),
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'tmdb',
                'tmdb_id' => 603,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('message', __('admin.collection.metadata.metadata_imported'))
            ->assertJsonPath('data.title', 'The Matrix')
            ->assertJsonPath('data.external_tmdb_id', 603)
            ->assertJsonMissingPath('data.cover_path')
            ->assertJsonPath('warnings.0', __('admin.collection.metadata.poster_import_failed'));
    }

    public function test_tmdb_import_maps_tv_series_metadata_and_imports_a_poster_locally(): void
    {
        config([
            'services.tmdb.api_key' => 'test-key',
            'services.tmdb.region' => 'FR',
        ]);

        Http::fake([
            'https://api.themoviedb.org/3/tv/63639*' => Http::response([
                ...$this->tmdbTvSeriesPayload(),
                'poster_path' => '/expanse.jpg',
                'aggregate_credits' => [
                    'cast' => [
                        ['name' => 'Steven Strait'],
                        ['name' => 'Shohreh Aghdashloo'],
                        ['name' => 'Dominique Tipper'],
                        ['name' => 'Wes Chatham'],
                        ['name' => 'Cas Anvar'],
                        ['name' => 'Frankie Adams'],
                    ],
                ],
                'content_ratings' => [
                    'results' => [
                        ['iso_3166_1' => 'US', 'rating' => 'TV-14'],
                        ['iso_3166_1' => 'FR', 'rating' => '16'],
                    ],
                ],
            ], 200),
            'https://image.tmdb.org/t/p/w500/expanse.jpg' => Http::response('poster-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'tmdb',
                'type' => 'tv_series',
                'tmdb_id' => 63639,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'tmdb')
            ->assertJsonPath('data.type', 'tv_series')
            ->assertJsonPath('data.title', 'The Expanse')
            ->assertJsonPath('data.original_title', 'The Expanse')
            ->assertJsonPath('data.release_year', 2015)
            ->assertJsonPath('data.end_year', 2022)
            ->assertJsonPath('data.season_count', 6)
            ->assertJsonPath('data.episode_count', 62)
            ->assertJsonPath('data.runtime_minutes', 45)
            ->assertJsonPath('data.showrunner', 'Mark Fergus, Hawk Ostby')
            ->assertJsonPath('data.network', 'Syfy, Prime Video')
            ->assertJsonPath('data.age_rating', '16')
            ->assertJsonPath('data.genres', ['Science Fiction', 'Drama'])
            ->assertJsonPath('data.cast_members', [
                'Steven Strait',
                'Shohreh Aghdashloo',
                'Dominique Tipper',
                'Wes Chatham',
                'Cas Anvar',
            ])
            ->assertJsonPath('data.external_tmdb_id', 63639)
            ->assertJsonMissingPath('data.physical_format')
            ->assertJsonMissingPath('data.edition')
            ->assertJsonMissingPath('data.region')
            ->assertJsonMissingPath('data.barcode')
            ->assertJsonMissingPath('data.condition')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.location');

        $coverPath = $response->json('data.cover_path');

        $this->assertIsString($coverPath);
        $this->assertTrue(str_starts_with($coverPath, 'covers/'));
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_igdb_import_maps_video_game_metadata_and_imports_a_cover_locally(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
        ]);

        Http::fake([
            'https://api.igdb.com/v4/games' => Http::response([$this->igdbGamePayload()], 200),
            'https://images.igdb.com/igdb/image/upload/t_cover_big/co1234.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'igdb',
                'igdb_id' => 1234,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('source', 'igdb')
            ->assertJsonPath('data.type', 'video_game')
            ->assertJsonPath('data.title', 'Super Mario Galaxy')
            ->assertJsonPath('data.release_year', 2007)
            ->assertJsonPath('data.platform', 'Wii, Wii U')
            ->assertJsonPath('data.developer', 'Nintendo EAD Tokyo')
            ->assertJsonPath('data.publisher', 'Nintendo')
            ->assertJsonPath('data.description', 'Mario explores space.')
            ->assertJsonPath('data.genres', ['Platform', 'Adventure'])
            ->assertJsonPath('data.modes', ['Single player'])
            ->assertJsonPath('data.external_igdb_id', 1234)
            ->assertJsonMissingPath('data.description_original')
            ->assertJsonMissingPath('data.external_tmdb_id')
            ->assertJsonMissingPath('data.barcode');

        $coverPath = $response->json('data.cover_path');

        $this->assertIsString($coverPath);
        $this->assertTrue(str_starts_with($coverPath, 'covers/'));
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_igdb_import_translates_description_to_the_admin_locale_when_a_provider_is_configured(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
            'services.translation.provider' => FakeTextTranslationProvider::class,
            'services.translation.source_locale' => 'en',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        Http::fake([
            'https://api.igdb.com/v4/games' => Http::response([$this->igdbGamePayload()], 200),
            'https://images.igdb.com/igdb/image/upload/t_cover_big/co1234.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'igdb',
                'igdb_id' => 1234,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.description', '[fr] Mario explores space.')
            ->assertJsonPath('data.description_original', 'Mario explores space.');
    }

    public function test_igdb_import_uses_google_translation_when_configured(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
            'services.translation.provider' => 'google',
            'services.translation.source_locale' => 'en',
            'services.translation.google.api_key' => 'google-test-key',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        Http::fake([
            'https://api.igdb.com/v4/games' => Http::response([$this->igdbGamePayload()], 200),
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Mario explore l&#39;espace.',
                            'detectedSourceLanguage' => 'en',
                        ],
                    ],
                ],
            ], 200),
            'https://images.igdb.com/igdb/image/upload/t_cover_big/co1234.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'igdb',
                'igdb_id' => 1234,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.description', "Mario explore l'espace.")
            ->assertJsonPath('data.description_original', 'Mario explores space.');

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2?key=google-test-key')
                && str_contains($request->body(), 'target=fr')
                && ! str_contains($request->body(), 'source=');
        });
    }

    public function test_igdb_import_keeps_original_description_and_warns_when_translation_provider_is_missing(): void
    {
        config([
            'services.igdb.client_id' => 'igdb-client',
            'services.igdb.access_token' => 'igdb-token',
            'services.translation.provider' => '',
            'services.translation.source_locale' => 'en',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'fr'])->save();

        Http::fake([
            'https://api.igdb.com/v4/games' => Http::response([$this->igdbGamePayload()], 200),
            'https://images.igdb.com/igdb/image/upload/t_cover_big/co1234.jpg' => Http::response('cover-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'igdb',
                'igdb_id' => 1234,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.description', 'Mario explores space.')
            ->assertJsonMissingPath('data.description_original')
            ->assertJsonPath('warnings.0', __('admin.collection.metadata.translation_not_configured'));
    }

    public function test_barcode_search_reports_no_barcode_metadata_source_and_does_not_fail_for_a_known_code(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'barcode',
                'barcode' => '5050582720433',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'no_source')
            ->assertJsonPath('message', __('admin.collection.metadata.barcode_source_unavailable'));
    }

    public function test_barcode_search_requires_a_barcode(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'barcode',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.collection.lookup.enter_barcode_to_search'));
    }

    public function test_barcode_search_rejects_invalid_barcodes(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'barcode',
                'barcode' => 'fast and furious',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.collection.lookup.enter_valid_barcode'));
    }

    public function test_barcode_import_returns_local_collection_metadata_when_available(): void
    {
        config(['barcode.providers' => [LocalItemBarcodeLookupProvider::class]]);
        Storage::disk('public')->put('covers/barcode-import.jpg', 'cover-bytes');

        Item::factory()->film()->create([
            'title' => 'Barcode import title',
            'original_title' => 'Barcode import original title',
            'release_year' => 2007,
            'barcode' => '5050582720433',
            'cover_path' => 'covers/barcode-import.jpg',
            'physical_format' => 'dvd',
            'condition' => 'good',
            'status' => 'owned',
            'description' => 'Barcode import description',
            'runtime_minutes' => 98,
            'studio' => 'Barcode Studio',
            'genres' => ['Drama'],
        ]);

        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.import'), [
                'source' => 'barcode',
                'barcode' => '5050582720433',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.title', 'Barcode import title')
            ->assertJsonPath('data.cover_path', 'covers/barcode-import.jpg')
            ->assertJsonPath('data.cover_url', Storage::disk('public')->url('covers/barcode-import.jpg'))
            ->assertJsonPath('data.description', 'Barcode import description');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tmdbSearchResults(int $start, int $count): array
    {
        $results = [];

        for ($id = $start; $id < $start + $count; $id++) {
            $results[] = [
                'id' => $id,
                'title' => 'Movie '.$id,
                'original_title' => 'Movie '.$id,
                'release_date' => '1999-01-01',
                'overview' => 'Movie overview '.$id,
            ];
        }

        return $results;
    }

    private function bggManySearchPayload(int $count): string
    {
        $items = '';

        for ($id = 1; $id <= $count; $id++) {
            $items .= sprintf(
                '    <item type="boardgame" id="%d"><name type="primary" value="Game %d" /><yearpublished value="2000" /></item>'."\n",
                $id,
                $id,
            );
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<items total=\"{$count}\">\n{$items}</items>";
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function bggManyThingPayload(array $ids): string
    {
        $items = '';

        foreach ($ids as $id) {
            if ($id < 1) {
                continue;
            }

            $items .= sprintf(
                '    <item type="boardgame" id="%d"><name type="primary" value="Game %d" /><description>Game %d description.</description><yearpublished value="2000" /></item>'."\n",
                $id,
                $id,
                $id,
            );
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<items>\n{$items}</items>";
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbMoviePayload(): array
    {
        return [
            'id' => 603,
            'title' => 'The Matrix',
            'original_title' => 'The Matrix',
            'overview' => 'A computer hacker learns about the true nature of reality.',
            'release_date' => '1999-03-31',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbTvSeriesPayload(): array
    {
        return [
            'id' => 63639,
            'name' => 'The Expanse',
            'original_name' => 'The Expanse',
            'overview' => 'A missing woman links a detective, a ship captain, and a conspiracy.',
            'first_air_date' => '2015-12-14',
            'last_air_date' => '2022-01-14',
            'in_production' => false,
            'number_of_seasons' => 6,
            'number_of_episodes' => 62,
            'episode_run_time' => [45],
            'created_by' => [
                ['name' => 'Mark Fergus'],
                ['name' => 'Hawk Ostby'],
            ],
            'networks' => [
                ['name' => 'Syfy'],
                ['name' => 'Prime Video'],
            ],
            'genres' => [
                ['name' => 'Science Fiction'],
                ['name' => 'Drama'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function igdbGamePayload(): array
    {
        return [
            'id' => 1234,
            'name' => 'Super Mario Galaxy',
            'summary' => 'Mario explores space.',
            'first_release_date' => 1194393600,
            'cover' => [
                'url' => '//images.igdb.com/igdb/image/upload/t_thumb/co1234.jpg',
            ],
            'platforms' => [
                ['name' => 'Wii'],
                ['name' => 'Wii U'],
            ],
            'involved_companies' => [
                [
                    'developer' => true,
                    'publisher' => false,
                    'company' => ['name' => 'Nintendo EAD Tokyo'],
                ],
                [
                    'developer' => false,
                    'publisher' => true,
                    'company' => ['name' => 'Nintendo'],
                ],
            ],
            'genres' => [
                ['name' => 'Platform'],
                ['name' => 'Adventure'],
            ],
            'game_modes' => [
                ['name' => 'Single player'],
            ],
            'age_ratings' => [
                [
                    'rating_category' => [
                        'rating' => '3',
                        'organization' => ['name' => 'PEGI'],
                    ],
                ],
            ],
        ];
    }

    private function bggSearchPayload(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<items total="1">
    <item type="boardgame" id="13">
        <name type="primary" value="CATAN" />
        <yearpublished value="1995" />
    </item>
</items>
XML;
    }

    private function bggThingPayload(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<items>
    <item type="boardgame" id="13">
        <thumbnail>https://cf.geekdo-images.com/catan-thumb.jpg</thumbnail>
        <image>https://cf.geekdo-images.com/catan.jpg</image>
        <name type="primary" value="CATAN" />
        <description>Trade, build, and settle.</description>
        <yearpublished value="1995" />
        <minplayers value="3" />
        <maxplayers value="4" />
        <playingtime value="120" />
        <minage value="10" />
        <link type="boardgamecategory" id="1026" value="Negotiation" />
        <link type="boardgamecategory" id="1021" value="Economic" />
        <link type="boardgamemechanic" id="2072" value="Dice Rolling" />
        <link type="boardgamemechanic" id="2008" value="Trading" />
        <link type="boardgamedesigner" id="7" value="Klaus Teuber" />
        <link type="boardgamepublisher" id="37" value="Kosmos" />
        <link type="boardgamepublisher" id="31418" value="Catan Studio" />
    </item>
</items>
XML;
    }

    private function bggTranslatedTextFromRequest(string $body): string
    {
        parse_str($body, $payload);

        return match ($payload['q'] ?? '') {
            'Trade, build, and settle.' => 'Echangez, construisez et colonisez.',
            'Negotiation' => 'Negociation',
            'Economic' => 'Economique',
            'Dice Rolling' => 'Lancer de des',
            'Trading' => 'Commerce',
            default => (string) ($payload['q'] ?? ''),
        };
    }

    private function bggMonopolySearchPayload(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<items total="4">
    <item type="boardgame" id="1932">
        <name type="primary" value="Anti-Monopoly" />
        <yearpublished value="1973" />
    </item>
    <item type="boardgame" id="13186">
        <name type="primary" value="Monopoly Junior" />
        <yearpublished value="1990" />
    </item>
    <item type="boardgame" id="3689">
        <name type="primary" value="Monopoly: Star Wars" />
        <yearpublished value="1997" />
    </item>
    <item type="boardgame" id="1406">
        <name type="primary" value="Monopoly" />
        <yearpublished value="1935" />
    </item>
</items>
XML;
    }

    private function bggMonopolyThingPayload(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<items>
    <item type="boardgame" id="1406">
        <thumbnail>https://cf.geekdo-images.com/monopoly-thumb.jpg</thumbnail>
        <image>https://cf.geekdo-images.com/monopoly.jpg</image>
        <name type="primary" value="Monopoly" />
        <description>Buy, sell, and trade properties.</description>
        <yearpublished value="1935" />
        <minplayers value="2" />
        <maxplayers value="8" />
        <playingtime value="180" />
        <minage value="8" />
    </item>
    <item type="boardgame" id="1932">
        <name type="primary" value="Anti-Monopoly" />
        <description>A real estate game with a twist.</description>
        <yearpublished value="1973" />
    </item>
    <item type="boardgame" id="13186">
        <name type="primary" value="Monopoly Junior" />
        <description>A simplified version for younger players.</description>
        <yearpublished value="1990" />
    </item>
    <item type="boardgame" id="3689">
        <name type="primary" value="Monopoly: Star Wars" />
        <description>A licensed edition.</description>
        <yearpublished value="1997" />
    </item>
</items>
XML;
    }
}
