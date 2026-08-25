<?php

namespace App\Services\Metadata;

use App\Services\ExternalServices\ExternalServiceSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TmdbMovieSearchService
{
    private const SEARCH_PAGE_SIZE = 10;

    public function __construct(
        private readonly MetadataImportMapper $mapper = new MetadataImportMapper(),
        private readonly ?ExternalServiceSettings $settings = null,
    ) {
    }

    public function configured(): bool
    {
        return filled($this->secret('api_key', 'services.tmdb.api_key'))
            || filled($this->secret('bearer_token', 'services.tmdb.bearer_token'));
    }

    public function search(string $title, ?int $releaseYear = null, int $page = 1): MetadataLookupResult
    {
        $title = trim($title);
        $page = max(1, $page);

        if ($title === '') {
            return MetadataLookupResult::invalid(__('admin.collection.validation.title_required'));
        }

        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.tmdb_not_configured'));
        }

        try {
            $response = $this->client()
                ->get('/search/movie', array_filter([
                    ...$this->authQueryParameters(),
                    'query' => $title,
                    'include_adult' => false,
                    'language' => $this->value('language', 'services.tmdb.language', 'fr-FR'),
                    'year' => $releaseYear,
                    'primary_release_year' => $releaseYear,
                    'page' => $this->tmdbApiPage($page),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if (! $response->successful()) {
                return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $response->status());
            }

            $payload = $response->json() ?? [];
            $candidates = collect($this->pageSlice($payload['results'] ?? [], $page))
                ->map(fn (array $candidate): array => $this->mapper->mapSearchCandidate($candidate, (string) config('services.tmdb.image_base_url')))
                ->values()
                ->all();
            $pagination = $this->pagination($payload, $page);

            if ($candidates === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                    'pagination' => $pagination,
                ], 'tmdb');
            }

            return MetadataLookupResult::found([
                'query' => $title,
                'release_year' => $releaseYear,
                'candidates' => $candidates,
                'pagination' => $pagination,
            ], __('admin.collection.metadata.results_found'), 'tmdb');
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    public function searchTvSeries(string $title, ?int $releaseYear = null, int $page = 1): MetadataLookupResult
    {
        $title = trim($title);
        $page = max(1, $page);

        if ($title === '') {
            return MetadataLookupResult::invalid(__('admin.collection.validation.title_required'));
        }

        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.tmdb_not_configured'));
        }

        try {
            $response = $this->client()
                ->get('/search/tv', array_filter([
                    ...$this->authQueryParameters(),
                    'query' => $title,
                    'include_adult' => false,
                    'language' => $this->value('language', 'services.tmdb.language', 'fr-FR'),
                    'first_air_date_year' => $releaseYear,
                    'page' => $this->tmdbApiPage($page),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if (! $response->successful()) {
                return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $response->status());
            }

            $payload = $response->json() ?? [];
            $candidates = collect($this->pageSlice($payload['results'] ?? [], $page))
                ->map(fn (array $candidate): array => $this->mapper->mapTvSearchCandidate($candidate, (string) config('services.tmdb.image_base_url')))
                ->values()
                ->all();
            $pagination = $this->pagination($payload, $page);

            if ($candidates === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                    'pagination' => $pagination,
                ], 'tmdb');
            }

            return MetadataLookupResult::found([
                'query' => $title,
                'release_year' => $releaseYear,
                'candidates' => $candidates,
                'pagination' => $pagination,
            ], __('admin.collection.metadata.results_found'), 'tmdb');
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    public function importMovie(int $tmdbId): MetadataLookupResult
    {
        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.tmdb_not_configured'));
        }

        try {
            $movie = $this->movieBundle($tmdbId);
            $credits = Arr::get($movie, 'credits', []);
            $releaseDates = Arr::get($movie, 'release_dates', []);
            $data = $this->mapper->mapTmdbMovie($movie, $credits, $releaseDates);
            $warnings = [];

            if (($movie['poster_path'] ?? null) && ! empty($movie['poster_path'])) {
                $posterImport = $this->importPoster(
                    (string) $movie['poster_path'],
                    (int) $tmdbId,
                    (string) ($movie['title'] ?? $tmdbId)
                );

                if (filled($posterImport['cover_path'] ?? null)) {
                    $data['cover_path'] = $posterImport['cover_path'];
                    $data['cover_url'] = $posterImport['cover_url'];
                } elseif (filled($posterImport['warning'] ?? null)) {
                    $warnings[] = (string) $posterImport['warning'];
                }
            }

            return MetadataLookupResult::found($data, __('admin.collection.metadata.metadata_imported'), 'tmdb', $warnings);
        } catch (RequestException $exception) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $exception->response->status() ?: 500);
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    public function importTvSeries(int $tmdbId): MetadataLookupResult
    {
        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.tmdb_not_configured'));
        }

        try {
            $series = $this->tvSeriesBundle($tmdbId);
            $credits = Arr::get($series, 'aggregate_credits', []);
            $contentRatings = Arr::get($series, 'content_ratings', []);
            $data = $this->mapper->mapTmdbTvSeries($series, $credits, $contentRatings);
            $warnings = [];

            if (($series['poster_path'] ?? null) && ! empty($series['poster_path'])) {
                $posterImport = $this->importPoster(
                    (string) $series['poster_path'],
                    (int) $tmdbId,
                    (string) ($series['name'] ?? $tmdbId)
                );

                if (filled($posterImport['cover_path'] ?? null)) {
                    $data['cover_path'] = $posterImport['cover_path'];
                    $data['cover_url'] = $posterImport['cover_url'];
                } elseif (filled($posterImport['warning'] ?? null)) {
                    $warnings[] = (string) $posterImport['warning'];
                }
            }

            return MetadataLookupResult::found($data, __('admin.collection.metadata.metadata_imported'), 'tmdb', $warnings);
        } catch (RequestException $exception) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $exception->response->status() ?: 500);
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function movieBundle(int $tmdbId): array
    {
        $response = $this->client()
            ->get("/movie/{$tmdbId}", [
                ...$this->authQueryParameters(),
                'language' => $this->value('language', 'services.tmdb.language', 'fr-FR'),
                'append_to_response' => 'credits,release_dates',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function tvSeriesBundle(int $tmdbId): array
    {
        $response = $this->client()
            ->get("/tv/{$tmdbId}", [
                ...$this->authQueryParameters(),
                'language' => $this->value('language', 'services.tmdb.language', 'fr-FR'),
                'append_to_response' => 'aggregate_credits,content_ratings',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    private function tmdbApiPage(int $page): int
    {
        return (int) floor(($page - 1) / 2) + 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageSlice(mixed $results, int $page): array
    {
        if (! is_array($results)) {
            return [];
        }

        $offset = ($page - 1) % 2 === 0 ? 0 : self::SEARCH_PAGE_SIZE;

        return array_slice($results, $offset, self::SEARCH_PAGE_SIZE);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pagination(array $payload, int $page): array
    {
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        $apiPage = $this->tmdbApiPage($page);
        $totalPages = max(1, (int) ($payload['total_pages'] ?? $apiPage));
        $offset = ($page - 1) % 2 === 0 ? 0 : self::SEARCH_PAGE_SIZE;
        $hasMoreInCurrentApiPage = count($results) > $offset + self::SEARCH_PAGE_SIZE;
        $hasMore = $hasMoreInCurrentApiPage || $apiPage < $totalPages;

        return [
            'current_page' => $page,
            'per_page' => self::SEARCH_PAGE_SIZE,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function importPoster(string $posterPath, int $tmdbId, string $title): array
    {
        $baseUrl = rtrim((string) config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p/w500'), '/');
        $posterUrl = $baseUrl.'/'.ltrim($posterPath, '/');

        try {
            $response = Http::accept('image/*')
                ->timeout((int) config('barcode.cover_timeout', 8))
                ->get($posterUrl);

            if (! $response->successful()) {
                return ['warning' => __('admin.collection.metadata.poster_import_failed')];
            }

            $contentType = (string) $response->header('Content-Type', '');
            $extension = $this->extensionForContentType($contentType, $posterUrl);
            $path = sprintf(
                'covers/tmdb-%s-%s.%s',
                Str::slug($title !== '' ? $title : (string) $tmdbId),
                Str::lower(Str::random(8)),
                $extension,
            );

            Storage::disk('public')->put($path, $response->body());

            return [
                'cover_path' => $path,
                'cover_url' => Storage::disk('public')->url($path),
            ];
        } catch (Throwable) {
            return ['warning' => __('admin.collection.metadata.poster_import_failed')];
        }
    }

    private function extensionForContentType(string $contentType, string $url): string
    {
        $normalized = strtolower(trim(strtok($contentType, ';') ?: ''));

        return match ($normalized) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => $this->extensionFromUrl($url),
        };
    }

    private function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::baseUrl('https://api.themoviedb.org/3')
            ->acceptJson()
            ->timeout((int) config('barcode.cover_timeout', 8));

        $bearerToken = $this->secret('bearer_token', 'services.tmdb.bearer_token');

        if ($bearerToken !== '') {
            return $client->withToken($bearerToken);
        }

        return $client;
    }

    /**
     * @return array<string, string>
     */
    private function authQueryParameters(): array
    {
        $apiKey = $this->secret('api_key', 'services.tmdb.api_key');

        return $apiKey !== '' ? ['api_key' => $apiKey] : [];
    }

    private function value(string $key, string $configKey, ?string $default = null): string
    {
        return trim((string) $this->settings()->get('tmdb', $key, config($configKey, $default)));
    }

    private function secret(string $key, string $configKey): string
    {
        return trim((string) $this->settings()->getSecret('tmdb', $key, config($configKey, '')));
    }

    private function settings(): ExternalServiceSettings
    {
        return $this->settings ?? app(ExternalServiceSettings::class);
    }
}
