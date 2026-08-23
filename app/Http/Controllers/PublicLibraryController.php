<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemLoan;
use App\Services\Library\LibrarySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PublicLibraryController extends Controller
{
    public function home(Request $request, LibrarySettings $librarySettings): View
    {
        $enabledTypes = $this->enabledTypes($librarySettings);
        $loansEnabled = $librarySettings->loansEnabled();

        if (! Schema::hasTable('items')) {
            return view('library.home', [
                'libraryName' => $librarySettings->libraryName(),
                'navigation' => $this->navigation($librarySettings, 'home'),
                'accentTheme' => $librarySettings->accentTheme(),
                'enabledTypes' => $enabledTypes,
                'dashboardStats' => $this->emptyDashboardStats($enabledTypes, $loansEnabled),
                'recentItems' => collect(),
                'loansEnabled' => $loansEnabled,
            ]);
        }

        $baseQuery = $this->libraryItemsQuery($librarySettings);

        return view('library.home', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'home'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $enabledTypes,
            'dashboardStats' => $this->dashboardStats($librarySettings, $baseQuery, $enabledTypes, $loansEnabled),
            'recentItems' => $this->recentItems($librarySettings),
            'loansEnabled' => $loansEnabled,
        ]);
    }

    public function type(Request $request, LibrarySettings $librarySettings, string $type): View
    {
        $itemType = $this->itemTypeOrFail($librarySettings, $type);
        $filters = $this->filters($request, $librarySettings, $itemType);

        $items = $this->applyFilters(
            $this->withActiveLoans(Item::query()->where('type', $itemType->value)),
            $filters,
        )
            ->paginate(36)
            ->withQueryString();

        return view('library.type', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, $itemType->value),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'type' => $itemType,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings, $itemType),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function favorites(Request $request, LibrarySettings $librarySettings): View
    {
        $filters = $this->filters($request, $librarySettings);

        $items = $this->applyFilters(
            $this->libraryItemsQuery($librarySettings)->where('is_favorite', true),
            $filters,
        )
            ->paginate(36)
            ->withQueryString();

        return view('library.favorites', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'favorites'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function search(Request $request, LibrarySettings $librarySettings): RedirectResponse|View
    {
        $filters = $this->filters($request, $librarySettings);
        $searchReturn = $this->searchReturn($request, $librarySettings);

        if ($filters['search'] === null) {
            return redirect()->to($this->searchReturnUrl($searchReturn));
        }

        $items = $this->applyFilters($this->searchItemsQuery($librarySettings, $searchReturn), $filters)
            ->paginate(36)
            ->withQueryString();

        return view('library.collection', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, $searchReturn),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'title' => __('library.sections.search_results'),
            'routeName' => 'library.search',
            'searchReturn' => $searchReturn,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function recent(Request $request, LibrarySettings $librarySettings): View
    {
        $filters = $this->filters($request, $librarySettings);

        $items = $this->applyFilters($this->libraryItemsQuery($librarySettings), $filters)
            ->paginate(36)
            ->withQueryString();

        return view('library.collection', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'recent'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'title' => __('library.sections.recent'),
            'routeName' => 'library.recent',
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function genres(Request $request, LibrarySettings $librarySettings): View
    {
        $filters = $this->filters($request, $librarySettings, null, 'title');

        $items = $this->applyFilters($this->libraryItemsQuery($librarySettings), $filters)
            ->paginate(36)
            ->withQueryString();

        return view('library.collection', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'genre'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'title' => __('library.navigation.genre'),
            'routeName' => 'library.genres',
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function years(Request $request, LibrarySettings $librarySettings): View
    {
        $filters = $this->filters($request, $librarySettings, null, 'year');

        $items = $this->applyFilters($this->libraryItemsQuery($librarySettings), $filters)
            ->paginate(36)
            ->withQueryString();

        return view('library.collection', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'year'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'title' => __('library.navigation.year'),
            'routeName' => 'library.years',
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($librarySettings),
            'items' => $items,
            'loansEnabled' => $librarySettings->loansEnabled(),
        ]);
    }

    public function show(LibrarySettings $librarySettings, Item $item): View
    {
        if (! $librarySettings->isTypeEnabled($item->type)) {
            abort(404);
        }

        $item = $this->withActiveLoans(Item::query()->whereKey($item))->firstOrFail();

        return view('library.show', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, $item->type->value),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'item' => $item,
            'activeLoan' => $librarySettings->loansEnabled() ? $item->itemLoans->first() : null,
            'loansEnabled' => $librarySettings->loansEnabled(),
            'locationsEnabled' => $librarySettings->locationsEnabled(),
        ]);
    }

    public function loans(LibrarySettings $librarySettings): View
    {
        if (! $librarySettings->loansEnabled()) {
            abort(404);
        }

        return view('library.loans', [
            'libraryName' => $librarySettings->libraryName(),
            'navigation' => $this->navigation($librarySettings, 'loans'),
            'accentTheme' => $librarySettings->accentTheme(),
            'enabledTypes' => $this->enabledTypes($librarySettings),
            'activeLoans' => $this->activeLoans($librarySettings)->paginate(24),
        ]);
    }

    /**
     * @return array<int, ItemType>
     */
    private function enabledTypes(LibrarySettings $librarySettings): array
    {
        return array_values(array_filter(
            ItemType::cases(),
            static fn (ItemType $type): bool => $librarySettings->isTypeEnabled($type),
        ));
    }

    /**
     * @return array{search: ?string, sort: string, type: ?string, favorite: bool, availability: ?string, year: ?int, genre: ?string}
     */
    private function filters(
        Request $request,
        LibrarySettings $librarySettings,
        ?ItemType $fixedType = null,
        string $defaultSort = 'recent',
    ): array
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString();
        $type = $fixedType?->value;
        $favorite = $request->boolean('favorite');
        $availability = $request->string('availability')->toString();
        $year = $request->integer('year');
        $genre = trim((string) $request->string('genre'));

        if (! in_array($sort, ['recent', 'title', 'year'], true)) {
            $sort = in_array($defaultSort, ['recent', 'title', 'year'], true) ? $defaultSort : 'recent';
        }

        if ($fixedType === null) {
            $requestedType = ItemType::tryFrom($request->string('type')->toString());

            if ($requestedType && $librarySettings->isTypeEnabled($requestedType)) {
                $type = $requestedType->value;
            }
        }

        if (! in_array($availability, ['loaned', 'available'], true)) {
            $availability = null;
        }

        if ($year < 1) {
            $year = null;
        }

        return [
            'search' => $search !== '' ? $search : null,
            'sort' => $sort,
            'type' => $type,
            'favorite' => $favorite,
            'availability' => $availability,
            'year' => $year,
            'genre' => $genre !== '' ? $genre : null,
        ];
    }

    /**
     * @param  Builder<Item>  $query
     * @param  array{search: ?string, sort: string, type: ?string, favorite: bool, availability: ?string, year: ?int, genre: ?string}  $filters
     * @return Builder<Item>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if ($filters['favorite']) {
            $query->where('is_favorite', true);
        }

        if ($filters['availability'] === 'loaned') {
            $query->whereHas('itemLoans', fn (Builder $loanQuery) => $loanQuery->active());
        } elseif ($filters['availability'] === 'available') {
            $query->whereDoesntHave('itemLoans', fn (Builder $loanQuery) => $loanQuery->active());
        }

        if ($filters['year'] !== null) {
            $query->where('release_year', $filters['year']);
        }

        if ($filters['genre'] !== null) {
            $query->whereJsonContains('genres', $filters['genre']);
        }

        if ($filters['search'] !== null) {
            $terms = $this->searchTerms($filters['search']);

            $query->where(function (Builder $subQuery) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.mb_strtolower($term).'%';

                    $subQuery->orWhereRaw('LOWER(title) like ?', [$like])
                        ->orWhereRaw('LOWER(original_title) like ?', [$like])
                        ->orWhereRaw('LOWER(description) like ?', [$like])
                        ->orWhereRaw('LOWER(barcode) like ?', [$like]);
                }
            });
        }

        return match ($filters['sort']) {
            'title' => $query->orderByRaw('LOWER(COALESCE(sort_title, title)) asc'),
            'year' => $query->orderByDesc('release_year')->orderBy('title'),
            default => $query->orderByDesc('created_at')->orderBy('title'),
        };
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $search): array
    {
        $terms = preg_split('/[\s,;:._\-]+/u', trim($search)) ?: [];
        $terms = array_values(array_unique(array_filter(
            array_map(static fn (string $term): string => trim($term), $terms),
            static fn (string $term): bool => $term !== '',
        )));

        $significantTerms = array_values(array_filter(
            $terms,
            static fn (string $term): bool => ! in_array(mb_strtolower($term), ['a', 'an', 'and', 'de', 'des', 'du', 'dans', 'et', 'la', 'le', 'les', 'of', 'the', 'un', 'une'], true),
        ));

        return $significantTerms !== [] ? $significantTerms : $terms;
    }

    private function searchReturn(Request $request, LibrarySettings $librarySettings): string
    {
        $return = $request->string('from')->toString();

        if (in_array($return, ['favorites', 'recent'], true)) {
            return $return;
        }

        $type = ItemType::tryFrom($return);

        return $type && $librarySettings->isTypeEnabled($type) ? $type->value : 'recent';
    }

    private function searchReturnUrl(string $searchReturn): string
    {
        return match ($searchReturn) {
            'favorites' => route('library.favorites'),
            'recent' => route('library.recent'),
            default => route('library.type', $searchReturn),
        };
    }

    /**
     * Start a search from the collection the visitor was browsing, rather than
     * from every item in the library.
     *
     * @return Builder<Item>
     */
    private function searchItemsQuery(LibrarySettings $librarySettings, string $searchReturn): Builder
    {
        if ($searchReturn === 'favorites') {
            return $this->libraryItemsQuery($librarySettings)->where('is_favorite', true);
        }

        $type = ItemType::tryFrom($searchReturn);

        if ($type) {
            return $this->withActiveLoans(Item::query()->where('type', $type->value));
        }

        return $this->libraryItemsQuery($librarySettings);
    }

    /**
     * @return Builder<Item>
     */
    private function libraryItemsQuery(LibrarySettings $librarySettings): Builder
    {
        return $this->withActiveLoans(
            Item::query()->whereIn('type', $librarySettings->enabledTypeValues()),
        );
    }

    /**
     * Load current loans for a poster or detail card without relying on the item's cached status.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    private function withActiveLoans(Builder $query): Builder
    {
        return $query->with([
            'itemLoans' => fn ($loanQuery) => $loanQuery->active(),
        ]);
    }

    private function recentItems(LibrarySettings $librarySettings)
    {
        return $this->libraryItemsQuery($librarySettings)
            ->orderByDesc('created_at')
            ->orderBy('title')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{types: array<int, ItemType>, genres: array<int, string>, years: array<int, int>}
     */
    private function filterOptions(LibrarySettings $librarySettings, ?ItemType $fixedType = null): array
    {
        $types = $this->enabledTypes($librarySettings);

        if (! Schema::hasTable('items')) {
            return $this->emptyFilterOptions($types);
        }

        $query = $this->libraryItemsQuery($librarySettings);

        if ($fixedType) {
            $query->where('type', $fixedType->value);
        }

        $years = (clone $query)
            ->whereNotNull('release_year')
            ->distinct()
            ->orderByDesc('release_year')
            ->pluck('release_year')
            ->map(fn ($year): int => (int) $year)
            ->values()
            ->all();

        $genres = (clone $query)
            ->whereNotNull('genres')
            ->pluck('genres')
            ->flatMap(fn ($genres): array => is_array($genres) ? $genres : [])
            ->filter(fn ($genre): bool => is_string($genre) && trim($genre) !== '')
            ->map(fn (string $genre): string => trim($genre))
            ->unique(fn (string $genre): string => strtolower($genre))
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values()
            ->all();

        return [
            'types' => $types,
            'genres' => $genres,
            'years' => $years,
        ];
    }

    /**
     * @param  array<int, ItemType>  $types
     * @return array{types: array<int, ItemType>, genres: array<int, string>, years: array<int, int>}
     */
    private function emptyFilterOptions(array $types): array
    {
        return [
            'types' => $types,
            'genres' => [],
            'years' => [],
        ];
    }

    private function activeLoans(LibrarySettings $librarySettings): Builder
    {
        return ItemLoan::query()
            ->with('item')
            ->active()
            ->whereHas('item', fn (Builder $query) => $query->whereIn('type', $librarySettings->enabledTypeValues()))
            ->latest('loaned_at');
    }

    /**
     * @param  Builder<Item>  $baseQuery
     * @param  array<int, ItemType>  $enabledTypes
     * @return array<int, array{key: string, label: string, value: int, href: string}>
     */
    private function dashboardStats(
        LibrarySettings $librarySettings,
        Builder $baseQuery,
        array $enabledTypes,
        bool $loansEnabled,
    ): array
    {
        $stats = [
            [
                'key' => 'total',
                'label' => __('library.stats.total'),
                'value' => (clone $baseQuery)->count(),
                'href' => route('library.recent'),
            ],
        ];

        foreach ($enabledTypes as $type) {
            $stats[] = [
                'key' => $type->value,
                'label' => __('library.navigation.'.$type->value),
                'value' => Item::query()->where('type', $type->value)->count(),
                'href' => route('library.type', $type->value),
            ];
        }

        if ($loansEnabled) {
            $stats[] = [
                'key' => 'loaned',
                'label' => __('library.stats.loaned'),
                'value' => $this->activeLoans($librarySettings)->count(),
                'href' => route('library.loans'),
            ];
        }

        $stats[] = [
            'key' => 'favorites',
            'label' => __('library.stats.favorites'),
            'value' => (clone $baseQuery)->where('is_favorite', true)->count(),
            'href' => route('library.favorites'),
        ];

        return $stats;
    }

    /**
     * @param  array<int, ItemType>  $enabledTypes
     * @return array<int, array{key: string, label: string, value: int, href: string}>
     */
    private function emptyDashboardStats(array $enabledTypes, bool $loansEnabled): array
    {
        $stats = [
            [
                'key' => 'total',
                'label' => __('library.stats.total'),
                'value' => 0,
                'href' => route('library.recent'),
            ],
        ];

        foreach ($enabledTypes as $type) {
            $stats[] = [
                'key' => $type->value,
                'label' => __('library.navigation.'.$type->value),
                'value' => 0,
                'href' => route('library.type', $type->value),
            ];
        }

        if ($loansEnabled) {
            $stats[] = [
                'key' => 'loaned',
                'label' => __('library.stats.loaned'),
                'value' => 0,
                'href' => route('library.loans'),
            ];
        }

        $stats[] = [
            'key' => 'favorites',
            'label' => __('library.stats.favorites'),
            'value' => 0,
            'href' => route('library.favorites'),
        ];

        return $stats;
    }

    private function itemTypeOrFail(LibrarySettings $librarySettings, string $type): ItemType
    {
        $itemType = ItemType::tryFrom($type);

        if (! $itemType || ! $librarySettings->isTypeEnabled($itemType)) {
            abort(404);
        }

        return $itemType;
    }

    /**
     * @return array<int, array{key: string, href: ?string, active: bool, disabled?: bool}>
     */
    private function navigation(LibrarySettings $librarySettings, string $activeKey): array
    {
        $items = [
            [
                'key' => 'home',
                'href' => route('library.home'),
                'active' => $activeKey === 'home',
            ],
        ];

        foreach ($this->enabledTypes($librarySettings) as $type) {
            $items[] = [
                'key' => $type->value,
                'href' => route('library.type', $type->value),
                'active' => $activeKey === $type->value,
            ];
        }

        if ($librarySettings->loansEnabled()) {
            $items[] = [
                'key' => 'loans',
                'href' => route('library.loans'),
                'active' => $activeKey === 'loans',
            ];
        }

        $items[] = [
            'key' => 'favorites',
            'href' => route('library.favorites'),
            'active' => $activeKey === 'favorites',
        ];

        $items[] = [
            'key' => 'wishlist',
            'href' => null,
            'active' => false,
            'disabled' => true,
        ];

        return $items;
    }
}
