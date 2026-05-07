<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>Admin - ShelfVault</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center px-5 py-10">
            <a href="/" class="text-sm font-semibold text-emerald-700">ShelfVault</a>
            <section class="mt-5 rounded-lg border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">Admin</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Administration coming later</h1>
                <p class="mt-4 leading-7 text-stone-700">
                    This temporary route confirms the Laravel foundation is in place. Authentication and admin screens are intentionally left for a later ticket.
                </p>
                <a
                    href="/"
                    class="mt-6 inline-flex rounded-md bg-stone-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:ring-offset-2"
                >
                    Back to home
                </a>
            </section>
        </main>
    </body>
</html>
