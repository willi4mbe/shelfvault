@php
    $searchReturn = $searchReturn ?? 'recent';
    $sortPrefix = filled($filters['search'] ?? null)
        ? '?q='.urlencode($filters['search']).'&from='.urlencode($searchReturn).'&sort='
        : '?sort=';
@endphp

<div class="library-page-actions">
    <div class="library-listing-controls">
        <form method="GET" action="{{ route('library.search') }}" class="library-search-form">
            <input type="hidden" name="from" value="{{ $searchReturn }}">
            <label>
                <span class="sr-only">{{ __('library.search.label') }}</span>
                <input
                    type="search"
                    name="q"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="{{ __('library.search.placeholder') }}"
                    onsearch="if (this.value.trim() === '') this.form.submit()"
                >
            </label>
            <button type="submit">
                @include('admin.icon', ['name' => 'search', 'class' => 'h-4 w-4'])
                <span>{{ __('library.search.submit') }}</span>
            </button>
        </form>
        <div class="library-secondary-controls">
            <label class="library-sort-control">
                <span>{{ __('library.sort.label') }}</span>
                <select aria-label="{{ __('library.sort.label') }}" onchange="window.location.assign(this.value)">
                    <option value="{{ $actionUrl }}{{ $sortPrefix }}recent" @selected($filters['sort'] === 'recent')>{{ __('library.sort.recent') }}</option>
                    <option value="{{ $actionUrl }}{{ $sortPrefix }}title" @selected($filters['sort'] === 'title')>{{ __('library.sort.title') }}</option>
                    <option value="{{ $actionUrl }}{{ $sortPrefix }}year" @selected($filters['sort'] === 'year')>{{ __('library.sort.year') }}</option>
                </select>
            </label>

            <div
                class="library-density-control"
                x-data="{
                    density: localStorage.getItem('libraryPosterDensity') || '1',
                    apply() {
                        document.documentElement.dataset.posterDensity = this.density;
                        localStorage.setItem('libraryPosterDensity', this.density);
                    }
                }"
                x-init="apply(); $watch('density', () => apply())"
            >
                <label>
                    <span>{{ __('library.display.size') }}</span>
                    <input type="range" min="0" max="2" step="1" x-model="density" aria-label="{{ __('library.display.size') }}">
                </label>
                <span class="library-density-value" x-text="{0: @js(__('library.display.compact')), 1: @js(__('library.display.normal')), 2: @js(__('library.display.large'))}[density]"></span>
            </div>
        </div>
    </div>
</div>
