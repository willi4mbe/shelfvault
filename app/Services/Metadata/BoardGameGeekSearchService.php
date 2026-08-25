<?php

namespace App\Services\Metadata;

use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\MetadataTextTranslator;
use App\Services\Translation\Providers\NullTextTranslationProvider;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BoardGameGeekSearchService
{
    private const BASE_URL = 'https://boardgamegeek.com/xmlapi2';
    private const CANDIDATE_LIMIT = 12;
    private const DETAIL_LOOKUP_LIMIT = 13;

    public function __construct(
        private readonly ?TextTranslationProvider $translationProvider = null,
        private readonly ?MetadataTextTranslator $textTranslator = null,
        private readonly ?ExternalServiceSettings $settings = null,
    ) {
    }

    public function configured(): bool
    {
        return $this->token() !== '';
    }

    public function search(string $title, ?int $releaseYear = null, int $page = 1): MetadataLookupResult
    {
        $title = trim($title);
        $page = max(1, $page);

        if ($title === '') {
            return MetadataLookupResult::invalid(__('admin.collection.validation.title_required'));
        }

        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.bgg_not_configured'));
        }

        try {
            $response = $this->client()->get('/search', [
                'query' => $title,
                'type' => 'boardgame',
            ]);

            if ($this->authorizationFailed($response->status())) {
                return MetadataLookupResult::error(__('admin.collection.metadata.bgg_forbidden'), $response->status());
            }

            if (! $response->successful()) {
                return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $response->status());
            }

            $matches = $this->rankedSearchMatches($response->body(), $title, $releaseYear);

            if ($matches === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                    'pagination' => $this->pagination([], $page, 0),
                ], 'bgg');
            }

            $candidates = $this->thingCandidates($matches, $title, $releaseYear, $page, app()->getLocale());
            $pagination = $this->pagination($matches, $page, count($candidates));

            if ($candidates === []) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'query' => $title,
                    'release_year' => $releaseYear,
                    'candidates' => [],
                    'pagination' => $pagination,
                ], 'bgg');
            }

            return MetadataLookupResult::found([
                'query' => $title,
                'release_year' => $releaseYear,
                'candidates' => $candidates,
                'pagination' => $pagination,
            ], __('admin.collection.metadata.results_found'), 'bgg');
        } catch (ConnectionException) {
            return MetadataLookupResult::error(__('admin.collection.metadata.bgg_timeout'), 504);
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    public function importGame(int $bggId, ?string $targetLocale = null): MetadataLookupResult
    {
        if (! $this->configured()) {
            return MetadataLookupResult::noSource(__('admin.collection.metadata.bgg_not_configured'));
        }

        if ($bggId < 1) {
            return MetadataLookupResult::invalid(__('admin.collection.metadata.no_result_found'));
        }

        try {
            $response = $this->client()->get('/thing', [
                'id' => $bggId,
                'type' => 'boardgame',
                'stats' => 1,
            ]);

            if ($this->authorizationFailed($response->status())) {
                return MetadataLookupResult::error(__('admin.collection.metadata.bgg_forbidden'), $response->status());
            }

            if (! $response->successful()) {
                return MetadataLookupResult::error(__('admin.collection.metadata.search_error'), $response->status());
            }

            $game = $this->firstThing($response->body());

            if ($game === null) {
                return MetadataLookupResult::notFound(__('admin.collection.metadata.no_result_found'), [
                    'bgg_id' => $bggId,
                ], 'bgg');
            }

            $data = $this->mapThing($game);
            $this->translateMetadata($data, $targetLocale ?? app()->getLocale());
            $warnings = [];

            if (filled($data['poster_url'] ?? null)) {
                $coverImport = $this->importCover(
                    (string) $data['poster_url'],
                    $bggId,
                    (string) ($data['title'] ?? $bggId),
                );

                if (filled($coverImport['cover_path'] ?? null)) {
                    $data['cover_path'] = $coverImport['cover_path'];
                    $data['cover_url'] = $coverImport['cover_url'];
                } elseif (filled($coverImport['warning'] ?? null)) {
                    $warnings[] = (string) $coverImport['warning'];
                }
            }

            unset($data['poster_url'], $data['thumbnail_url'], $data['bgg_id'], $data['categories'], $data['mechanisms']);

            return MetadataLookupResult::found($data, __('admin.collection.metadata.metadata_imported'), 'bgg', $warnings);
        } catch (ConnectionException) {
            return MetadataLookupResult::error(__('admin.collection.metadata.bgg_timeout'), 504);
        } catch (Throwable) {
            return MetadataLookupResult::error(__('admin.collection.metadata.search_error'));
        }
    }

    /**
     * @return array<int, array{id: int, title: string, normalized_title: string, year: ?int, score: int, year_score: int, index: int}>
     */
    private function rankedSearchMatches(string $xml, string $query, ?int $releaseYear): array
    {
        $xpath = $this->xpath($xml);

        if ($xpath === null) {
            return [];
        }

        $items = $xpath->query('/items/item');

        if ($items === false) {
            return [];
        }

        $matches = [];
        $seenIds = [];
        $query = trim($query);
        $index = 0;

        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $id = (int) $item->getAttribute('id');

            if ($id < 1 || isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $title = $this->primaryName($xpath, $item) ?? '';
            $normalizedTitle = $this->normalizeTitle($title);
            $year = $this->integerValue($this->valueAttribute($xpath, './yearpublished', $item));
            $yearScore = $releaseYear !== null && $year !== null ? abs($releaseYear - $year) : 0;

            $matches[] = [
                'id' => $id,
                'title' => $title,
                'normalized_title' => $normalizedTitle,
                'year' => $year,
                'score' => $this->titleScore($query, $title),
                'year_score' => $yearScore,
                'index' => $index++,
            ];
        }

        usort($matches, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: $a['year_score'] <=> $b['year_score']
                ?: $a['index'] <=> $b['index'];
        });

        return array_values($matches);
    }

    /**
     * @param  array<int, array{id: int, title: string, normalized_title: string, year: ?int, score: int, year_score: int, index: int}>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function thingCandidates(array $matches, string $query, ?int $releaseYear, int $page, ?string $targetLocale = null): array
    {
        $offset = ($page - 1) * self::CANDIDATE_LIMIT;
        $ids = array_map(
            static fn (array $match): int => $match['id'],
            array_slice($matches, $offset, self::DETAIL_LOOKUP_LIMIT),
        );

        $response = $this->client()->get('/thing', [
            'id' => implode(',', $ids),
            'type' => 'boardgame',
            'stats' => 1,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $xpath = $this->xpath($response->body());

        if ($xpath === null) {
            return [];
        }

        $items = $xpath->query('/items/item');

        if ($items === false) {
            return [];
        }

        $candidates = [];
        $seenIds = [];
        $seenTitles = [];
        $matchById = [];

        foreach ($matches as $match) {
            $matchById[$match['id']] = $match;
        }

        foreach ($items as $item) {
            if ($item instanceof DOMElement) {
                $data = $this->mapCandidate($item);
                $id = (int) ($data['bgg_id'] ?? 0);
                $normalizedTitle = $this->normalizeTitle((string) ($data['title'] ?? ''));

                if ($id < 1 || $normalizedTitle === '' || isset($seenIds[$id]) || isset($seenTitles[$normalizedTitle])) {
                    continue;
                }

                $seenIds[$id] = true;
                $seenTitles[$normalizedTitle] = true;
                $this->translateMetadata($data, $targetLocale ?? app()->getLocale(), 'overview');
                $year = isset($data['release_year']) ? (int) $data['release_year'] : null;
                $data['_lookup_score'] = $this->titleScore($query, (string) ($data['title'] ?? ''));
                $data['_lookup_year_score'] = $releaseYear !== null && $year !== null ? abs($releaseYear - $year) : ($matchById[$id]['year_score'] ?? 0);
                $data['_lookup_index'] = $matchById[$id]['index'] ?? count($candidates);
                $candidates[] = $data;
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            return $b['_lookup_score'] <=> $a['_lookup_score']
                ?: $a['_lookup_year_score'] <=> $b['_lookup_year_score']
                ?: $a['_lookup_index'] <=> $b['_lookup_index'];
        });

        return array_map(
            static function (array $candidate): array {
                unset($candidate['_lookup_score'], $candidate['_lookup_year_score'], $candidate['_lookup_index']);

                return $candidate;
            },
            array_slice(array_values(array_filter($candidates)), 0, self::CANDIDATE_LIMIT),
        );
    }

    /**
     * @param  array<int, array{id: int, title: string, normalized_title: string, year: ?int, score: int, year_score: int, index: int}>  $matches
     * @return array<string, mixed>
     */
    private function pagination(array $matches, int $page, int $candidateCount): array
    {
        $offset = ($page - 1) * self::CANDIDATE_LIMIT;
        $hasMore = $candidateCount > 0 && count($matches) > $offset + $candidateCount;

        return [
            'current_page' => $page,
            'per_page' => self::CANDIDATE_LIMIT,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
        ];
    }

    private function firstThing(string $xml): ?DOMElement
    {
        $xpath = $this->xpath($xml);

        if ($xpath === null) {
            return null;
        }

        $items = $xpath->query('/items/item');

        if ($items === false || $items->length < 1) {
            return null;
        }

        $item = $items->item(0);

        return $item instanceof DOMElement ? $item : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCandidate(DOMElement $item): array
    {
        $data = $this->mapThing($item);

        return array_filter([
            'source' => 'bgg',
            'type' => 'board_game',
            'bgg_id' => $data['bgg_id'] ?? null,
            'id' => $data['bgg_id'] ?? null,
            'title' => $data['title'] ?? null,
            'release_year' => $data['release_year'] ?? null,
            'overview' => $data['description'] ?? null,
            'poster_url' => $data['poster_url'] ?? $data['thumbnail_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'min_players' => $data['min_players'] ?? null,
            'max_players' => $data['max_players'] ?? null,
            'play_time_minutes' => $data['play_time_minutes'] ?? null,
            'age_rating' => $data['age_rating'] ?? null,
            'designer' => $data['designer'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'categories' => $data['categories'] ?? null,
            'mechanisms' => $data['mechanisms'] ?? null,
            'genres' => $data['genres'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapThing(DOMElement $item): array
    {
        $xpath = new DOMXPath($item->ownerDocument);
        $categories = $this->links($xpath, $item, 'boardgamecategory');
        $mechanisms = $this->links($xpath, $item, 'boardgamemechanic');
        $genres = array_values(array_unique([...$categories, ...$mechanisms]));
        $minAge = $this->valueAttribute($xpath, './minage', $item);

        return array_filter([
            'type' => 'board_game',
            'bgg_id' => (int) $item->getAttribute('id'),
            'title' => $this->primaryName($xpath, $item),
            'description' => html_entity_decode(trim($this->textContent($xpath, './description', $item) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: null,
            'release_year' => $this->integerValue($this->valueAttribute($xpath, './yearpublished', $item)),
            'poster_url' => $this->textContent($xpath, './image', $item),
            'thumbnail_url' => $this->textContent($xpath, './thumbnail', $item),
            'min_players' => $this->integerValue($this->valueAttribute($xpath, './minplayers', $item)),
            'max_players' => $this->integerValue($this->valueAttribute($xpath, './maxplayers', $item)),
            'play_time_minutes' => $this->integerValue($this->valueAttribute($xpath, './playingtime', $item)),
            'age_rating' => $minAge !== null ? $minAge.'+' : null,
            'designer' => $this->textFromList($this->links($xpath, $item, 'boardgamedesigner')),
            'publisher' => $this->textFromList($this->links($xpath, $item, 'boardgamepublisher')),
            'categories' => $categories,
            'mechanisms' => $mechanisms,
            'genres' => $genres,
            'sort_title' => Str::lower((string) $this->primaryName($xpath, $item)),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateMetadata(array &$data, ?string $targetLocale, string $descriptionKey = 'description'): void
    {
        $translator = $this->textTranslator();

        if (! $translator->shouldTranslate($targetLocale)) {
            return;
        }

        $this->translateTextField($data, $descriptionKey, $targetLocale);
        $this->translateListField($data, 'categories', $targetLocale);
        $this->translateListField($data, 'mechanisms', $targetLocale);

        if (isset($data['categories']) || isset($data['mechanisms'])) {
            $data['genres'] = array_values(array_unique([
                ...($data['categories'] ?? []),
                ...($data['mechanisms'] ?? []),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateTextField(array &$data, string $key, ?string $targetLocale): void
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $result = $this->textTranslator()->translate($value, $targetLocale, null);

        if ($result->translated && trim($result->text) !== '') {
            $data[$key] = $result->text;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translateListField(array &$data, string $key, ?string $targetLocale): void
    {
        $values = $data[$key] ?? null;

        if (! is_array($values) || $values === []) {
            return;
        }

        $translated = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $result = $this->textTranslator()->translate($value, $targetLocale, null);
            $translated[] = $result->translated && trim($result->text) !== '' ? $result->text : $value;
        }

        if ($translated !== []) {
            $data[$key] = array_values(array_unique($translated));
        }
    }

    private function xpath(string $xml): ?DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }

    private function primaryName(DOMXPath $xpath, DOMElement $context): ?string
    {
        return $this->valueAttribute($xpath, './name[@type="primary"]', $context)
            ?? $this->valueAttribute($xpath, './name', $context);
    }

    /**
     * @return array<int, string>
     */
    private function links(DOMXPath $xpath, DOMElement $context, string $type): array
    {
        $nodes = $xpath->query('./link[@type="'.$type.'"]', $context);

        if ($nodes === false) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $value = trim($node->getAttribute('value'));

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function valueAttribute(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $nodes = $xpath->query($query, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        $value = trim($node->getAttribute('value'));

        return $value !== '' ? $value : null;
    }

    private function textContent(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $nodes = $xpath->query($query, $context);
        $node = $nodes === false ? null : $nodes->item(0);
        $value = $node ? trim($node->textContent) : '';

        return $value !== '' ? $value : null;
    }

    private function integerValue(?string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeTitle(string $value): string
    {
        $value = Str::ascii(Str::lower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function titleScore(string $query, string $title): int
    {
        $query = $this->normalizeTitle($query);
        $title = $this->normalizeTitle($title);

        if ($query === '' || $title === '') {
            return 0;
        }

        if ($title === $query) {
            return 1000;
        }

        $score = 0;

        if (str_starts_with($title, $query.' ')) {
            $score = 820;
        } elseif (preg_match('/(^|\s)'.preg_quote($query, '/').'(\s|$)/', $title) === 1) {
            $score = 680;
        } elseif ($this->containsAllWords($title, $query)) {
            $score = 520;
        } else {
            similar_text($query, $title, $percent);
            $score = (int) round($percent * 3);
        }

        return max(0, $score - $this->variantPenalty($query, $title));
    }

    private function containsAllWords(string $title, string $query): bool
    {
        $titleWords = array_flip(explode(' ', $title));

        foreach (explode(' ', $query) as $word) {
            if ($word !== '' && ! isset($titleWords[$word])) {
                return false;
            }
        }

        return true;
    }

    private function variantPenalty(string $query, string $title): int
    {
        if ($title === $query) {
            return 0;
        }

        $queryWords = array_values(array_filter(explode(' ', $query)));
        $titleWords = array_values(array_filter(explode(' ', $title)));
        $penalty = max(0, count($titleWords) - count($queryWords)) * 25;

        if (count($queryWords) <= 2) {
            $variantWords = [
                'anti', 'junior', 'edition', 'expansion', 'fan', 'promo', 'promotional',
                'travel', 'card', 'cards', 'deluxe', 'collector', 'collectors', 'special',
                'anniversary', 'electronic', 'mega', 'giant', 'builder', 'deal', 'empire',
                'gamer', 'ultimate', 'city', 'disney', 'star', 'wars', 'here', 'now',
            ];

            foreach ($variantWords as $word) {
                if (in_array($word, $titleWords, true)) {
                    $penalty += 260;
                    break;
                }
            }

            if (str_starts_with($title, 'anti '.$query) || str_contains($title, ' expansion')) {
                $penalty += 180;
            }
        }

        return min($penalty, 520);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function textFromList(array $values): ?string
    {
        return $values === [] ? null : implode(', ', array_slice($values, 0, 5));
    }

    /**
     * @return array<string, string>
     */
    private function importCover(string $coverUrl, int $bggId, string $title): array
    {
        try {
            $response = Http::accept('image/*')
                ->timeout((int) config('barcode.cover_timeout', 8))
                ->get($coverUrl);

            if (! $response->successful()) {
                return ['warning' => __('admin.collection.metadata.bgg_cover_import_failed')];
            }

            $extension = $this->extensionForContentType((string) $response->header('Content-Type', ''), $coverUrl);
            $path = sprintf(
                'covers/bgg-%s-%s.%s',
                Str::slug($title !== '' ? $title : (string) $bggId),
                Str::lower(Str::random(8)),
                $extension,
            );

            Storage::disk('public')->put($path, $response->body());

            return [
                'cover_path' => $path,
                'cover_url' => Storage::disk('public')->url($path),
            ];
        } catch (Throwable) {
            return ['warning' => __('admin.collection.metadata.bgg_cover_import_failed')];
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
        return Http::baseUrl(self::BASE_URL)
            ->accept('application/xml,text/xml,*/*')
            ->withToken($this->token())
            ->timeout((int) config('barcode.cover_timeout', 8));
    }

    private function translator(): TextTranslationProvider
    {
        return $this->translationProvider ?? app(TextTranslationProvider::class) ?? new NullTextTranslationProvider();
    }

    private function textTranslator(): MetadataTextTranslator
    {
        return $this->textTranslator ?? new MetadataTextTranslator($this->translator());
    }

    private function token(): string
    {
        $token = trim((string) $this->settings()->getSecret('bgg', 'token', config('services.bgg.token', '')));

        return strtolower($token) === 'pending' ? '' : $token;
    }

    private function authorizationFailed(int $status): bool
    {
        return in_array($status, [401, 403, 429], true);
    }

    private function settings(): ExternalServiceSettings
    {
        return $this->settings ?? app(ExternalServiceSettings::class);
    }
}
