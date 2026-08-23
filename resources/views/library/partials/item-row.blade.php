<article class="library-loan-row">
    <a href="{{ route('library.items.show', $loan->item) }}" class="flex min-w-0 items-center gap-3">
        <span class="library-loan-cover">
            @if ($loan->item->coverUrl())
                <img src="{{ $loan->item->coverUrl() }}" alt="{{ $loan->item->title }}" class="h-full w-full object-cover">
            @else
                @include('admin.icon', ['name' => 'collection', 'class' => 'h-4 w-4'])
            @endif
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold text-white">{{ $loan->item->title }}</span>
            <span class="mt-1 block truncate text-xs text-white/48">
                {{ __('library.detail.loaned_to', ['name' => $loan->borrower_name]) }}
            </span>
        </span>
    </a>
    <span class="shrink-0 text-xs font-semibold text-white/54">
        {{ $loan->loaned_at?->format('d/m/Y') }}
    </span>
</article>
