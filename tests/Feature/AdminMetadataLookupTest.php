<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use App\Services\Metadata\MetadataImportMapper;
use App\Services\Metadata\Providers\LocalItemBarcodeLookupProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_title_search_rejects_unsupported_types_for_automatic_search(): void
    {
        $this->actingAs(User::query()->first())
            ->postJson(route('admin.collection.metadata.search'), [
                'mode' => 'title',
                'type' => 'video_game',
                'title' => 'The Matrix',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.collection.lookup.automatic_search_not_available_for_this_type'));
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
}
