@props([
    'item',
    'loansEnabled' => false,
])

@php
    $coverUrl = $item->coverUrl();
    $type = $item->type->value;
    $activeLoan = $loansEnabled && $item->relationLoaded('itemLoans')
        ? $item->itemLoans->first()
        : null;
@endphp

<article class="library-poster-card group">
    <a href="{{ route('library.items.show', $item) }}" class="block focus:outline-none" aria-label="{{ __('library.actions.open_item', ['title' => $item->title]) }}">
        <div class="library-poster-frame">
            @if ($coverUrl)
                <img src="{{ $coverUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
            @else
                <div class="library-poster-placeholder">
                    @include('admin.icon', ['name' => match ($type) {
                        'film' => 'films',
                        'tv_series' => 'tv_series',
                        'video_game' => 'video_games',
                        'board_game' => 'board_games',
                        default => 'collection',
                    }, 'class' => 'h-9 w-9'])
                    <span class="sr-only">{{ __('library.empty.no_cover') }}</span>
                </div>
            @endif

            <div class="library-poster-shade"></div>
            <span class="library-type-badge library-type-badge-{{ $type }}">{{ __('library.types.'.$type) }}</span>

            @if ($item->is_favorite)
                <span class="library-status-badge library-favorite-badge" title="{{ __('library.badges.favorite') }}" aria-label="{{ __('library.badges.favorite') }}">
                    @include('admin.icon', ['name' => 'heart', 'class' => 'h-3.5 w-3.5'])
                    <span class="sr-only">{{ __('library.badges.favorite') }}</span>
                </span>
            @endif

            @if ($activeLoan)
                <span class="library-status-badge library-loaned-badge">
                    {{ __('library.badges.loaned') }}
                </span>
            @endif
        </div>

        <div class="mt-3 min-w-0">
            <h3 class="library-card-title">{{ $item->title }}</h3>
            <p class="mt-1 text-xs font-medium text-white/46">
                {{ $item->release_year ?: __('library.types.'.$type) }}
            </p>
        </div>
    </a>
</article>
