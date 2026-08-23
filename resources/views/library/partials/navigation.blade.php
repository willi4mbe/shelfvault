<nav class="mt-8 min-h-0 flex-1 space-y-7 overflow-y-auto pr-1" aria-label="{{ __('library.navigation.label') }}">
    <div class="space-y-2">
@foreach ($navigation as $item)
        @php($icon = match ($item['key']) {
            'home' => 'home',
            'favorites' => 'recent_additions',
            'film' => 'films',
            'tv_series' => 'tv_series',
            'video_game' => 'video_games',
            'board_game' => 'board_games',
            'loans' => 'loans',
            'wishlist' => 'recent_additions',
            default => 'collection',
        })

        @if ($item['disabled'] ?? false)
            <span class="library-nav-link library-nav-link-disabled">
                <span class="library-nav-link-label">
                    @include('admin.icon', ['name' => $icon, 'class' => 'h-4 w-4'])
                    <span>{{ __('library.navigation.'.$item['key']) }}</span>
                </span>
                <span class="library-nav-soon">{{ __('library.navigation.wishlist_soon') }}</span>
            </span>
        @else
            <a
                href="{{ $item['href'] }}"
                @class([
                    'library-nav-link',
                    'library-nav-link-active' => $item['active'],
                ])
                @if($item['active']) aria-current="page" @endif
            >
                <span class="library-nav-link-label">
                    @include('admin.icon', ['name' => $icon, 'class' => 'h-4 w-4'])
                    <span>{{ __('library.navigation.'.$item['key']) }}</span>
                </span>
            </a>
        @endif
    @endforeach
    </div>
</nav>
