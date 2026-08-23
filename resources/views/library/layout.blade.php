<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#050507">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-192.png') }}" type="image/png" sizes="192x192">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-512.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <title>@yield('title', __('library.meta.title', ['name' => $libraryName]))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="library-body library-accent-{{ $accentTheme['key'] ?? 'orange' }} min-h-screen text-white antialiased"
        style="--library-accent: {{ $accentTheme['rgb'] ?? '249 115 22' }}; --library-accent-contrast: {{ $accentTheme['contrastRgb'] ?? '23 13 2' }};"
    >
        <div x-data="{ menuOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[16.5rem_minmax(0,1fr)]">
            <header class="library-mobile-header sticky top-0 z-40 flex items-center justify-between gap-3 px-4 py-3 lg:hidden">
                <a href="{{ route('library.home') }}" class="flex min-w-0 items-center gap-3">
                    <span class="library-mobile-logo">
                        <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-7 w-7">
                    </span>
                    <span class="truncate text-sm font-semibold">{{ $libraryName }}</span>
                </a>
                <button
                    type="button"
                    x-on:click="menuOpen = ! menuOpen"
                    class="library-icon-button"
                    aria-controls="library-mobile-menu"
                    x-bind:aria-expanded="menuOpen.toString()"
                >
                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-5 w-5'])
                    <span class="sr-only">{{ __('library.navigation.menu') }}</span>
                </button>
            </header>

            <aside class="library-sidebar hidden min-h-screen flex-col overflow-hidden px-5 py-6 lg:flex">
                <a href="{{ route('library.home') }}" class="library-brand">
                    <span class="library-logo-mark">
                        <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-8 w-8">
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold uppercase text-white/46">{{ __('library.brand') }}</span>
                        <span class="mt-1 block truncate text-base font-semibold text-white">{{ $libraryName }}</span>
                    </span>
                </a>

                @include('library.partials.navigation', ['navigation' => $navigation])

                <div class="library-sidebar-footer">
                    <a href="{{ route('login') }}" class="library-admin-link">
                        @include('admin.icon', ['name' => 'auth', 'class' => 'h-4 w-4'])
                        <span>{{ __('library.navigation.admin') }}</span>
                    </a>
                </div>
            </aside>

            <div
                x-cloak
                x-show="menuOpen"
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-50 bg-black/72 lg:hidden"
                x-on:click.self="menuOpen = false"
            >
                <aside id="library-mobile-menu" class="library-drawer flex h-full w-[min(22rem,86vw)] flex-col px-5 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('library.home') }}" class="library-brand">
                            <span class="library-logo-mark">
                                <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-8 w-8">
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold uppercase text-white/46">{{ __('library.brand') }}</span>
                                <span class="mt-1 block truncate text-base font-semibold text-white">{{ $libraryName }}</span>
                            </span>
                        </a>
                        <button type="button" x-on:click="menuOpen = false" class="library-icon-button">
                            @include('admin.icon', ['name' => 'minimize', 'class' => 'h-5 w-5'])
                            <span class="sr-only">{{ __('library.navigation.menu') }}</span>
                        </button>
                    </div>

                    @include('library.partials.navigation', ['navigation' => $navigation])

                    <div class="library-sidebar-footer">
                        <a href="{{ route('login') }}" class="library-admin-link">
                            @include('admin.icon', ['name' => 'auth', 'class' => 'h-4 w-4'])
                            <span>{{ __('library.navigation.admin') }}</span>
                        </a>
                    </div>
                </aside>
            </div>

            <main class="min-w-0 px-4 pb-10 pt-5 sm:px-6 lg:px-9 lg:py-7">
                <div class="mx-auto max-w-[96rem]">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
