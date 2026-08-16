<?php

namespace App\Services\Metadata;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class IgdbVideoGameSearchService
{
    public function __construct(
        private readonly MetadataImportMapper $mapper = new MetadataImportMapper(),
    ) {
    }

    public function configured(): bool
    {
        $clientId = trim((string) config('services.igdb.client_id', ''));
        $accessToken = trim((string) config('services.igdb.access_token', ''));
        $clientSecret = trim((string) config('services.igdb.client_secret', ''));

        return $clientId !== '' && ($accessToken !== '' || $clientSecret !== '');
    }

    public function search(string $title, ?int $releaseYear = null): MetadataLookupResult
    {
        $title = trim($title);

        if ($title === '') {
            return MetadataLookupResult::invalid(__('admin.collection.validation.title_required'));
        }

        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.igdb_not_configured'));
        }

        try {
            $games = $this->games($this->searchQuery($title, $releaseYear));
            $candidates = collect($games)
                ->take(5)
                ->map(fn (array $candidate): array => $this->mapper->mapIgdbSearchCandidate($candidate))
                ->values()
                ->all();

            if ($candidates === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                ], 'igdb');
            }

            return MetadataLookupResult::found([
                'query' => $title,
                'release_year' => $releaseYear,
                'candidates' => $candidates,
            ], __('admin.collection.metadata.results_found'), 'igdb');
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    public function importGame(int $igdbId): MetadataLookupResult
    {
        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.igdb_not_configured'));
        }

        try {
            $games = $this->games($this->importQuery($igdbId));
            $game = $games[0] ?? [];

            if ($game === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'igdb_id' => $igdbId,
                ], 'igdb');
            }

            $data = $this->mapper->mapIgdbGame($game);
            $warnings = [];
            $coverUrl = $this->coverUrl($game);

            if ($coverUrl !== null) {
                $coverImport = $this->importCover(
                    $coverUrl,
                    $igdbId,
                    (string) ($game['name'] ?? $igdbId),
                );

                if (filled($coverImport['cover_path'] ?? null)) {
                    $data['cover_path'] = $coverImport['cover_path'];
                    $data['cover_url'] = $coverImport['cover_url'];
                } elseif (filled($coverImport['warning'] ?? null)) {
                    $warnings[] = (string) $coverImport['warning'];
                }
            }

            return MetadataLookupResult::found($data, __('admin.collection.metadata.metadata_imported'), 'igdb', $warnings);
        } catch (RequestException $exception) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $exception->response->status() ?: 500);
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function games(string $query): array
    {
        $response = $this->client()
            ->withBody($query, 'text/plain')
            ->post('/games');

        $response->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function searchQuery(string $title, ?int $releaseYear): string
    {
        $query = sprintf(
            'search "%s"; fields %s;',
            $this->escapeQueryString($title),
            $this->fields(),
        );

        if ($releaseYear !== null) {
            $start = strtotime($releaseYear.'-01-01 00:00:00 UTC');
            $end = strtotime(($releaseYear + 1).'-01-01 00:00:00 UTC') - 1;

            if ($start !== false && $end !== false) {
                $query .= sprintf(' where first_release_date >= %d & first_release_date <= %d;', $start, $end);
            }
        }

        return $query.' limit 5;';
    }

    private function importQuery(int $igdbId): string
    {
        return sprintf(
            'fields %s; where id = %d; limit 1;',
            $this->fields(),
            $igdbId,
        );
    }

    private function fields(): string
    {
        return implode(',', [
            'id',
            'name',
            'summary',
            'storyline',
            'first_release_date',
            'cover.url',
            'platforms.name',
            'involved_companies.company.name',
            'involved_companies.developer',
            'involved_companies.publisher',
            'genres.name',
            'game_modes.name',
            'age_ratings.rating_category.rating',
            'age_ratings.rating_category.organization.name',
        ]);
    }

    private function escapeQueryString(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * @param  array<string, mixed>  $game
     */
    private function coverUrl(array $game): ?string
    {
        $url = Arr::get($game, 'cover.url');

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $normalized = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $size = trim((string) config('services.igdb.image_size', 'cover_big'));

        return preg_replace('/\/t_[^\/]+\//', '/t_'.$size.'/', $normalized) ?: $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function importCover(string $coverUrl, int $igdbId, string $title): array
    {
        try {
            $response = Http::accept('image/*')
                ->timeout((int) config('barcode.cover_timeout', 8))
                ->get($coverUrl);

            if (! $response->successful()) {
                return ['warning' => __('admin.collection.metadata.igdb_cover_import_failed')];
            }

            $contentType = (string) $response->header('Content-Type', '');
            $extension = $this->extensionForContentType($contentType, $coverUrl);
            $path = sprintf(
                'covers/igdb-%s-%s.%s',
                Str::slug($title !== '' ? $title : (string) $igdbId),
                Str::lower(Str::random(8)),
                $extension,
            );

            Storage::disk('public')->put($path, $response->body());

            return [
                'cover_path' => $path,
                'cover_url' => Storage::disk('public')->url($path),
            ];
        } catch (Throwable) {
            return ['warning' => __('admin.collection.metadata.igdb_cover_import_failed')];
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
        return Http::baseUrl(rtrim((string) config('services.igdb.base_url'), '/'))
            ->acceptJson()
            ->withHeaders([
                'Client-ID' => trim((string) config('services.igdb.client_id', '')),
                'Authorization' => 'Bearer '.$this->accessToken(),
            ])
            ->timeout((int) config('barcode.cover_timeout', 8));
    }

    private function accessToken(): string
    {
        $configuredToken = trim((string) config('services.igdb.access_token', ''));

        if ($configuredToken !== '') {
            return $configuredToken;
        }

        return Cache::remember('shelfvault.igdb.access_token', now()->addHours(12), function (): string {
            $response = Http::asForm()
                ->acceptJson()
                ->post((string) config('services.igdb.token_url'), [
                    'client_id' => trim((string) config('services.igdb.client_id', '')),
                    'client_secret' => trim((string) config('services.igdb.client_secret', '')),
                    'grant_type' => 'client_credentials',
                ]);

            $response->throw();

            return (string) $response->json('access_token', '');
        });
    }
}
