<?php

namespace App\Services\Metadata;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TmdbMovieSearchService
{
    public function __construct(
        private readonly MetadataImportMapper $mapper = new MetadataImportMapper(),
    ) {
    }

    public function configured(): bool
    {
        return filled((string) config('services.tmdb.api_key', ''))
            || filled((string) config('services.tmdb.bearer_token', ''));
    }

    public function search(string $title, ?int $releaseYear = null): MetadataLookupResult
    {
        $title = trim($title);

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
                    'language' => config('services.tmdb.language', 'fr-FR'),
                    'year' => $releaseYear,
                    'primary_release_year' => $releaseYear,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if (! $response->successful()) {
                return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $response->status());
            }

            $candidates = collect($response->json('results', []))
                ->take(5)
                ->map(fn (array $candidate): array => $this->mapper->mapSearchCandidate($candidate, (string) config('services.tmdb.image_base_url')))
                ->values()
                ->all();

            if ($candidates === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                ], 'tmdb');
            }

            return MetadataLookupResult::found([
                'query' => $title,
                'release_year' => $releaseYear,
                'candidates' => $candidates,
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

    /**
     * @return array<string, mixed>
     */
    private function movieBundle(int $tmdbId): array
    {
        $response = $this->client()
            ->get("/movie/{$tmdbId}", [
                ...$this->authQueryParameters(),
                'language' => config('services.tmdb.language', 'fr-FR'),
                'append_to_response' => 'credits,release_dates',
            ]);

        $response->throw();

        return $response->json() ?? [];
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

        $bearerToken = trim((string) config('services.tmdb.bearer_token', ''));

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
        $apiKey = trim((string) config('services.tmdb.api_key', ''));

        return $apiKey !== '' ? ['api_key' => $apiKey] : [];
    }
}
