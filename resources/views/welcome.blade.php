<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#111827">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-192.png') }}" type="image/png" sizes="192x192">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-512.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <title>{{ __('welcome.meta.title') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-6 sm:px-8 lg:px-10">
            <header class="flex items-center justify-between gap-4">
                <a href="/" class="text-lg font-semibold tracking-tight">ShelfVault</a>
                <a
                    href="{{ route('login') }}"
                    class="rounded-md bg-stone-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:ring-offset-2"
                >
                    {{ __('welcome.actions.admin') }}
                </a>
            </header>

            <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr]">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">{{ __('welcome.hero.eyebrow') }}</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-tight text-stone-950 sm:text-5xl">
                        {{ __('welcome.hero.title') }}
                    </h1>
                    <p class="mt-5 text-lg leading-8 text-stone-700">
                        {{ __('welcome.hero.subtitle') }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            {{ __('welcome.actions.primary') }}
                        </a>
                        <a
                            href="https://laravel.com/docs"
                            class="inline-flex items-center justify-center rounded-md border border-stone-300 px-5 py-3 text-sm font-semibold text-stone-800 transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:ring-offset-2"
                        >
                            {{ __('welcome.actions.secondary') }}
                        </a>
                    </div>
                </div>

                @php
                    $welcomeTabs = __('welcome.tabs');
                    $welcomeTabLabels = collect($welcomeTabs)->mapWithKeys(fn (array $item, string $key) => [$key => $item['label']])->all();
                @endphp
                <div
                    x-data="{ active: 'films', labels: @js($welcomeTabLabels) }"
                    class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex rounded-md bg-stone-100 p-1 text-sm font-medium text-stone-600">
                        @foreach ($welcomeTabs as $key => $tab)
                            <button
                                type="button"
                                x-on:click="active = '{{ $key }}'"
                                x-bind:class="active === '{{ $key }}' ? 'bg-white text-stone-950 shadow-sm' : 'hover:text-stone-950'"
                                class="flex-1 rounded px-3 py-2 transition"
                            >
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-md border border-stone-200 p-4">
                            <div>
                                <p class="text-sm font-semibold" x-text="labels[active]"></p>
                                <p class="mt-1 text-sm text-stone-500">{{ __('welcome.panel.subtitle') }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">{{ __('welcome.panel.badge') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md bg-stone-100 p-4">
                                <p class="font-semibold">{{ __('welcome.info.mobile.title') }}</p>
                                <p class="mt-1 text-stone-600">{{ __('welcome.info.mobile.subtitle') }}</p>
                            </div>
                            <div class="rounded-md bg-stone-100 p-4">
                                <p class="font-semibold">{{ __('welcome.info.private.title') }}</p>
                                <p class="mt-1 text-stone-600">{{ __('welcome.info.private.subtitle') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
