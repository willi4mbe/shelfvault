<?php

namespace App\Services\Metadata;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MetadataImportMapper
{
    /**
     * @param  array<string, mixed>  $movie
     * @param  array<string, mixed>  $credits
     * @param  array<string, mixed>  $releaseDates
     * @return array<string, mixed>
     */
    public function mapTmdbMovie(array $movie, array $credits = [], array $releaseDates = []): array
    {
        $releaseYear = $this->releaseYear($movie['release_date'] ?? null);
        $genres = collect(Arr::get($movie, 'genres', []))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
        $castMembers = collect(Arr::get($credits, 'cast', []))
            ->pluck('name')
            ->filter()
            ->take(5)
            ->values()
            ->all();
        $director = collect(Arr::get($credits, 'crew', []))
            ->first(fn (array $member): bool => ($member['job'] ?? null) === 'Director');

        return array_filter([
            'type' => 'film',
            'title' => Arr::get($movie, 'title'),
            'original_title' => Arr::get($movie, 'original_title'),
            'description' => Arr::get($movie, 'overview'),
            'release_year' => $releaseYear,
            'genres' => $genres !== [] ? $genres : null,
            'runtime_minutes' => Arr::get($movie, 'runtime'),
            'studio' => $this->productionCompany(Arr::get($movie, 'production_companies', [])),
            'cast_members' => $castMembers !== [] ? $castMembers : null,
            'director' => is_array($director) ? ($director['name'] ?? null) : null,
            'age_rating' => $this->ageRating($releaseDates),
            'external_tmdb_id' => Arr::get($movie, 'id'),
            'sort_title' => $this->sortTitle(Arr::get($movie, 'title')),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function mapSearchCandidate(array $candidate, string $imageBaseUrl): array
    {
        $posterPath = Arr::get($candidate, 'poster_path');

        return array_filter([
            'tmdb_id' => Arr::get($candidate, 'id'),
            'title' => Arr::get($candidate, 'title'),
            'original_title' => Arr::get($candidate, 'original_title'),
            'release_year' => $this->releaseYear(Arr::get($candidate, 'release_date')),
            'overview' => Arr::get($candidate, 'overview'),
            'poster_path' => $posterPath,
            'poster_url' => $posterPath ? rtrim($imageBaseUrl, '/').'/'.ltrim($posterPath, '/') : null,
            'vote_average' => Arr::get($candidate, 'vote_average'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    public function mapIgdbSearchCandidate(array $game): array
    {
        return array_filter([
            'source' => 'igdb',
            'igdb_id' => Arr::get($game, 'id'),
            'id' => Arr::get($game, 'id'),
            'title' => Arr::get($game, 'name'),
            'release_year' => $this->releaseYearFromTimestamp(Arr::get($game, 'first_release_date')),
            'overview' => Arr::get($game, 'summary') ?: Arr::get($game, 'storyline'),
            'poster_url' => $this->igdbCoverUrl(Arr::get($game, 'cover.url')),
            'platforms' => $this->namesFromNestedList(Arr::get($game, 'platforms', [])),
            'developer' => $this->companyName($game, 'developer'),
            'publisher' => $this->companyName($game, 'publisher'),
            'genres' => $this->namesFromNestedList(Arr::get($game, 'genres', [])),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    public function mapIgdbGame(array $game): array
    {
        $title = Arr::get($game, 'name');

        return array_filter([
            'type' => 'video_game',
            'title' => $title,
            'description' => Arr::get($game, 'summary') ?: Arr::get($game, 'storyline'),
            'release_year' => $this->releaseYearFromTimestamp(Arr::get($game, 'first_release_date')),
            'genres' => $this->namesFromNestedList(Arr::get($game, 'genres', [])),
            'platform' => $this->textFromList($this->namesFromNestedList(Arr::get($game, 'platforms', []))),
            'developer' => $this->companyName($game, 'developer'),
            'publisher' => $this->companyName($game, 'publisher'),
            'modes' => $this->namesFromNestedList(Arr::get($game, 'game_modes', [])),
            'age_rating' => $this->igdbAgeRating(Arr::get($game, 'age_ratings', [])),
            'external_igdb_id' => Arr::get($game, 'id'),
            'sort_title' => $this->sortTitle(is_string($title) ? $title : null),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function releaseYear(?string $releaseDate): ?int
    {
        if (! is_string($releaseDate) || trim($releaseDate) === '') {
            return null;
        }

        try {
            return Carbon::parse($releaseDate)->year;
        } catch (\Throwable) {
            return null;
        }
    }

    private function releaseYearFromTimestamp(mixed $timestamp): ?int
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp((int) $timestamp)->year;
        } catch (\Throwable) {
            return null;
        }
    }

    private function igdbCoverUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $normalized = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $size = trim((string) config('services.igdb.image_size', 'cover_big'));

        return preg_replace('/\/t_[^\/]+\//', '/t_'.$size.'/', $normalized) ?: $normalized;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function namesFromNestedList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): ?string => is_array($item) ? Arr::get($item, 'name') : null)
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     */
    private function textFromList(array $values): ?string
    {
        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * @param  array<string, mixed>  $game
     */
    private function companyName(array $game, string $role): ?string
    {
        $companies = Arr::get($game, 'involved_companies', []);

        foreach ($companies as $entry) {
            if (! is_array($entry) || ! (bool) Arr::get($entry, $role, false)) {
                continue;
            }

            $name = Arr::get($entry, 'company.name');

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $ratings
     */
    private function igdbAgeRating(array $ratings): ?string
    {
        foreach ($ratings as $rating) {
            if (! is_array($rating)) {
                continue;
            }

            $category = Arr::get($rating, 'rating_category.rating');
            $organization = Arr::get($rating, 'rating_category.organization.name');

            if (is_string($category) && trim($category) !== '') {
                return trim((is_string($organization) && trim($organization) !== '' ? $organization.' ' : '').$category);
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $companies
     */
    private function productionCompany(array $companies): ?string
    {
        foreach ($companies as $company) {
            $name = is_array($company) ? ($company['name'] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $releaseDates
     */
    private function ageRating(array $releaseDates): ?string
    {
        $region = strtoupper((string) config('services.tmdb.region', ''));
        $results = Arr::get($releaseDates, 'results', []);

        if ($region !== '') {
            foreach ($results as $releaseRegion) {
                $releaseRegionCode = strtoupper((string) Arr::get($releaseRegion, 'iso_3166_1', ''));

                if ($releaseRegionCode !== $region) {
                    continue;
                }

                $certification = $this->firstCertification(Arr::get($releaseRegion, 'release_dates', []));

                if ($certification !== null) {
                    return $certification;
                }
            }
        }

        foreach ($results as $releaseRegion) {
            $certification = $this->firstCertification(Arr::get($releaseRegion, 'release_dates', []));

            if ($certification !== null) {
                return $certification;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $releaseDates
     */
    private function firstCertification(array $releaseDates): ?string
    {
        foreach ($releaseDates as $releaseDate) {
            $certification = trim((string) Arr::get($releaseDate, 'certification', ''));

            if ($certification !== '') {
                return $certification;
            }
        }

        return null;
    }

    private function sortTitle(?string $title): ?string
    {
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return Str::lower(trim($title));
    }
}
