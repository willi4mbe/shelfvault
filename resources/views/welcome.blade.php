<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#111827">

        <title>ShelfVault</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-6 sm:px-8 lg:px-10">
            <header class="flex items-center justify-between gap-4">
                <a href="/" class="text-lg font-semibold tracking-tight">ShelfVault</a>
                <a
                    href="{{ route('admin.placeholder') }}"
                    class="rounded-md bg-stone-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:ring-offset-2"
                >
                    Admin
                </a>
            </header>

            <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr]">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">Self-hosted media catalog</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-tight text-stone-950 sm:text-5xl">
                        Your physical collection, self-hosted.
                    </h1>
                    <p class="mt-5 text-lg leading-8 text-stone-700">
                        ShelfVault is being built as a private, mobile-first Laravel app for films, video games, and board games.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="{{ route('admin.placeholder') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            Open admin placeholder
                        </a>
                        <a
                            href="https://laravel.com/docs"
                            class="inline-flex items-center justify-center rounded-md border border-stone-300 px-5 py-3 text-sm font-semibold text-stone-800 transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 focus:ring-offset-2"
                        >
                            Laravel documentation
                        </a>
                    </div>
                </div>

                <div
                    x-data="{ active: 'Films' }"
                    class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex rounded-md bg-stone-100 p-1 text-sm font-medium text-stone-600">
                        <template x-for="type in ['Films', 'Games', 'Board games']" :key="type">
                            <button
                                type="button"
                                x-on:click="active = type"
                                x-bind:class="active === type ? 'bg-white text-stone-950 shadow-sm' : 'hover:text-stone-950'"
                                class="flex-1 rounded px-3 py-2 transition"
                                x-text="type"
                            ></button>
                        </template>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-md border border-stone-200 p-4">
                            <div>
                                <p class="text-sm font-semibold" x-text="active"></p>
                                <p class="mt-1 text-sm text-stone-500">Manual cataloging first, optional enrichment later.</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">V1</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md bg-stone-100 p-4">
                                <p class="font-semibold">Mobile-first</p>
                                <p class="mt-1 text-stone-600">Ready for iOS and Android browsers.</p>
                            </div>
                            <div class="rounded-md bg-stone-100 p-4">
                                <p class="font-semibold">Private by default</p>
                                <p class="mt-1 text-stone-600">One admin account planned for V1.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
