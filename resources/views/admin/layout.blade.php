<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#111827">
        <meta name="robots" content="noindex">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-192.png') }}" type="image/png" sizes="192x192">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-512.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <title>@yield('title', __('admin.brand'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-body min-h-screen text-zinc-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-[96rem] px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
            <div class="admin-shell flex w-full flex-1 flex-col gap-4 lg:flex-row">
                <aside class="admin-sidebar flex min-h-0 flex-col overflow-hidden rounded-[28px] p-4 sm:p-5 lg:w-[19rem] lg:p-6 xl:w-[20rem]">
                    <div class="space-y-4">
                        <div class="admin-logo-strip inline-flex h-14 w-fit max-w-full items-center rounded-2xl px-4 py-2">
                            <img src="{{ asset('branding/shelfvault.png') }}" alt="{{ __('admin.brand') }}" class="block h-10 w-auto flex-none object-contain">
                        </div>

                        <div class="admin-sidebar-hero rounded-[24px] p-4">
                            <p class="admin-sidebar-title">
                                {{ __('admin.sidebar.title') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 min-h-0 flex-1 overflow-y-auto">
                        <nav class="space-y-2">
                            <p class="px-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/45">
                                {{ __('admin.sidebar.navigation') }}
                            </p>

                            <a href="{{ url('/') }}" class="admin-nav-link" aria-label="{{ __('admin.navigation.home') }}">
                                <span class="admin-nav-link-label">
                                    @include('admin.icon', ['name' => 'home', 'class' => 'admin-nav-link-icon'])
                                    <span>{{ __('admin.navigation.home') }}</span>
                                </span>
                            </a>

                            @isset($navigation)
                                @foreach ($navigation as $item)
                                    @if (($item['logout'] ?? false))
                                        <form method="POST" action="{{ route('admin.logout') }}">
                                            @csrf
                                            <button type="submit" class="admin-nav-link admin-nav-link-button">
                                                <span class="admin-nav-link-label">
                                                    @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-nav-link-icon'])
                                                    <span>{{ __('admin.actions.logout') }}</span>
                                                </span>
                                            </button>
                                        </form>
                                    @elseif (($item['interactive'] ?? false))
                                        <a
                                            href="{{ $item['route'] }}"
                                            @class([
                                                'admin-nav-link',
                                                'admin-nav-link-active' => ($item['active'] ?? false),
                                            ])
                                            @if(($item['active'] ?? false)) aria-current="page" @endif
                                        >
                                            <span class="admin-nav-link-label">
                                                @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-nav-link-icon'])
                                                <span>{{ __('admin.navigation.'.$item['key']) }}</span>
                                            </span>
                                        </a>
                                    @else
                                        <div class="admin-nav-link admin-nav-link-disabled">
                                            <span class="admin-nav-link-label">
                                                @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-nav-link-icon'])
                                                <span>{{ __('admin.navigation.'.$item['key']) }}</span>
                                            </span>
                                            @if (($item['soon'] ?? false))
                                                <span class="admin-nav-link-meta">{{ __('admin.dashboard.soon') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endisset
                        </nav>

                        @hasSection('sidebar')
                            <div class="mt-4">
                                @yield('sidebar')
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 border-t border-white/10 pt-4">
                        <span class="text-xs font-medium text-white/52">
                            {{ __('admin.footer.version') }}
                        </span>
                    </div>
                </aside>

                <section class="admin-main flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px]">
                    @hasSection('header')
                        <header class="shrink-0 border-b border-zinc-200/80">
                            @yield('header')
                        </header>
                    @endif

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5 lg:p-6">
                        @yield('content')
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
