@php
    $item = $loan->item;
    $coverUrl = $item?->coverUrl();
    $type = $item?->type?->value;
    $dateMode = $dateMode ?? 'expected_return';
    $secondaryDate = $dateMode === 'loaned_at' ? $loan->loaned_at : $loan->expected_return_at;
    $secondaryDateLabel = $dateMode === 'loaned_at'
        ? __('library.detail.loaned_since_short')
        : __('library.detail.expected_return_short');
@endphp

@if ($item)
    <article class="library-poster-card library-loan-card group">
        <a href="{{ route('library.items.show', $item) }}" class="block focus:outline-none" aria-label="{{ __('library.actions.open_item', ['title' => $item->title]) }}">
            <div class="library-poster-frame">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                @else
                    <div class="library-poster-placeholder">
                        @include('admin.icon', ['name' => 'collection', 'class' => 'h-9 w-9'])
                        <span class="sr-only">{{ __('library.empty.no_cover') }}</span>
                    </div>
                @endif

                <div class="library-poster-shade"></div>
                @if ($type)
                    <span class="library-type-badge library-type-badge-{{ $type }}">{{ __('library.types.'.$type) }}</span>
                @endif
                <div class="library-loan-overlay">
                    <span>
                        <span>{{ __('library.detail.loaned_to_short') }}</span>
                        <strong>{{ $loan->borrower_name }}</strong>
                    </span>
                    @if ($secondaryDate)
                        <span>
                            <span>{{ $secondaryDateLabel }}</span>
                            <strong>{{ $secondaryDate->format('d/m/Y') }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-3 min-w-0">
                <h3 class="library-card-title">{{ $item->title }}</h3>
                <p class="mt-1 text-xs font-medium text-white/46">
                    {{ $item->release_year ?: ($type ? __('library.types.'.$type) : __('library.navigation.loans')) }}
                </p>
            </div>
        </a>
    </article>
@endif
