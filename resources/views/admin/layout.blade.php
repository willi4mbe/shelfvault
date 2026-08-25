<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#050507">
        <meta name="robots" content="noindex">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-192.png') }}" type="image/png" sizes="192x192">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-512.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <title>@yield('title', __('admin.brand'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php($adminAccentTheme = app(\App\Services\Library\LibrarySettings::class)->accentTheme())
    <body
        class="admin-body library-body admin-accent-{{ $adminAccentTheme['key'] ?? 'orange' }} min-h-screen text-white antialiased"
        style="--library-accent: {{ $adminAccentTheme['rgb'] ?? '249 115 22' }}; --library-accent-contrast: {{ $adminAccentTheme['contrastRgb'] ?? '23 13 2' }}; --admin-accent: {{ $adminAccentTheme['rgb'] ?? '249 115 22' }}; --admin-accent-contrast: {{ $adminAccentTheme['contrastRgb'] ?? '23 13 2' }};"
    >
        <div x-data="{ menuOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[16.5rem_minmax(0,1fr)]">
            <header class="library-mobile-header sticky top-0 z-40 flex items-center justify-between gap-3 px-4 py-3 lg:hidden">
                <a href="{{ route('admin') }}" class="library-brand">
                    <span class="library-mobile-logo">
                        <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-7 w-7">
                    </span>
                    <span class="truncate text-sm font-semibold">{{ __('admin.brand') }}</span>
                </a>
                <button
                    type="button"
                    x-on:click="menuOpen = ! menuOpen"
                    class="library-icon-button"
                    aria-controls="admin-mobile-menu"
                    x-bind:aria-expanded="menuOpen.toString()"
                >
                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-5 w-5'])
                    <span class="sr-only">{{ __('library.navigation.menu') }}</span>
                </button>
            </header>

            <div
                x-cloak
                x-show="menuOpen"
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-50 bg-black/72 lg:hidden"
                x-on:click.self="menuOpen = false"
            >
                <aside id="admin-mobile-menu" class="library-drawer flex h-full w-[min(22rem,86vw)] flex-col px-5 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('admin') }}" class="library-brand">
                            <span class="library-logo-mark">
                                <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-8 w-8">
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold uppercase text-white/46">{{ __('admin.brand') }}</span>
                                <span class="mt-1 block truncate text-base font-semibold text-white">{{ __('admin.sidebar.title') }}</span>
                            </span>
                        </a>
                        <button type="button" x-on:click="menuOpen = false" class="library-icon-button">
                            @include('admin.icon', ['name' => 'minimize', 'class' => 'h-5 w-5'])
                            <span class="sr-only">{{ __('library.navigation.menu') }}</span>
                        </button>
                    </div>

                    <div class="mt-6 min-h-0 flex-1 overflow-y-auto">
                        @include('admin.partials.navigation')

                        @hasSection('sidebar')
                            <div class="mt-4">
                                @yield('sidebar')
                            </div>
                        @endif
                    </div>

                    <div class="library-sidebar-footer">
                        <span class="text-xs font-medium text-white/52">
                            {{ __('admin.footer.version', ['version' => config('shelfvault.version')]) }}
                        </span>
                    </div>
                </aside>
            </div>

            <aside class="library-sidebar admin-sidebar hidden min-h-screen flex-col overflow-hidden px-5 py-6 lg:flex">
                <a href="{{ route('admin') }}" class="library-brand">
                    <span class="library-logo-mark">
                        <img src="{{ asset('branding/shelfvault-icon-192.png') }}" alt="" class="h-8 w-8">
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold uppercase text-white/46">{{ __('admin.brand') }}</span>
                        <span class="mt-1 block truncate text-base font-semibold text-white">{{ __('admin.sidebar.title') }}</span>
                    </span>
                </a>

                <div class="mt-6 min-h-0 flex-1 overflow-y-auto">
                    @include('admin.partials.navigation')

                    @hasSection('sidebar')
                        <div class="mt-4">
                            @yield('sidebar')
                        </div>
                    @endif
                </div>

                <div class="library-sidebar-footer">
                    <span class="text-xs font-medium text-white/52">
                        {{ __('admin.footer.version', ['version' => config('shelfvault.version')]) }}
                    </span>
                </div>
            </aside>

            <main class="min-w-0 px-4 pb-10 pt-5 sm:px-6 lg:px-9 lg:py-7">
                <div class="mx-auto max-w-[96rem]">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
