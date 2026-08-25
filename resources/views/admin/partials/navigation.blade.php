@php($logoutItem = null)

<nav class="library-nav-group mt-6 space-y-2">
    <p class="admin-nav-heading">
        {{ __('admin.sidebar.navigation') }}
    </p>

    @isset($navigation)
        @foreach ($navigation as $item)
            @if (($item['logout'] ?? false))
                @php($logoutItem = $item)
            @elseif (($item['interactive'] ?? false))
                <a
                    href="{{ $item['route'] }}"
                    @class([
                        'library-nav-link admin-nav-link',
                        'library-nav-link-active admin-nav-link-active' => ($item['active'] ?? false),
                    ])
                    @if(($item['active'] ?? false)) aria-current="page" @endif
                >
                    <span class="library-nav-link-label admin-nav-link-label">
                        @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-nav-link-icon'])
                        <span>{{ __('admin.navigation.'.$item['key']) }}</span>
                    </span>
                </a>
            @else
                <div class="library-nav-link library-nav-link-disabled admin-nav-link admin-nav-link-disabled">
                    <span class="library-nav-link-label admin-nav-link-label">
                        @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-nav-link-icon'])
                        <span>{{ __('admin.navigation.'.$item['key']) }}</span>
                    </span>
                </div>
            @endif
        @endforeach
    @endisset
</nav>

<div class="mt-5 space-y-2 border-t border-white/10 pt-4">
    <a href="{{ url('/') }}" class="library-nav-link admin-nav-link admin-nav-link-secondary" aria-label="{{ __('admin.navigation.home') }}">
        <span class="library-nav-link-label admin-nav-link-label">
            @include('admin.icon', ['name' => 'home', 'class' => 'admin-nav-link-icon'])
            <span>{{ __('admin.navigation.home') }}</span>
        </span>
    </a>

    @if ($logoutItem)
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="library-nav-link admin-nav-link admin-nav-link-button admin-nav-link-secondary">
                <span class="library-nav-link-label admin-nav-link-label">
                    @include('admin.icon', ['name' => $logoutItem['icon'], 'class' => 'admin-nav-link-icon'])
                    <span>{{ __('admin.actions.logout') }}</span>
                </span>
            </button>
        </form>
    @endif
</div>
