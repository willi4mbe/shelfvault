<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#111827">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-192.png') }}" type="image/png" sizes="192x192">
        <link rel="icon" href="{{ asset('branding/shelfvault-icon-512.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <title>{{ __('install.brand') }} - {{ __('install.badge') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="install-body min-h-screen text-zinc-950 antialiased">
        @php
            $steps = ['requirements', 'database', 'admin'];
            $activeStep = $currentStep ?? 'requirements';
        @endphp

        <main class="relative mx-auto flex min-h-screen w-full items-stretch justify-center px-4 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-6">
            <div class="flex w-full max-w-6xl flex-1 flex-col gap-3 lg:h-[calc(100vh-3rem)] lg:max-h-[calc(100vh-3rem)]">
                <section class="install-frame grid min-h-0 w-full flex-1 items-stretch gap-3 p-2 sm:gap-4 sm:p-3 lg:grid-cols-[0.88fr_1.12fr]">
                    <aside class="install-brand-panel flex min-h-0 flex-col justify-between rounded-[28px] p-4 text-white sm:p-5 lg:p-6 xl:p-7">
                        <div class="max-w-xl">
                            <div class="install-logo-strip inline-flex h-14 w-fit max-w-full items-center rounded-2xl px-4 py-2">
                                <img src="{{ asset('branding/shelfvault.png') }}" alt="ShelfVault" class="block h-10 w-auto flex-none object-contain">
                            </div>

                            <div class="mt-5">
                                <div class="inline-flex rounded-full px-3 py-1 text-sm font-semibold text-white/80 install-mini-badge whitespace-nowrap">
                                    {{ __('install.badge') }}
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/52">
                                    {{ __('install.language.label') }}
                                </p>

                                <form method="POST" action="{{ route('install.locale') }}" class="grid grid-cols-2 gap-2">
                                    @csrf
                                    <button type="submit" name="locale" value="en" class="install-language-option {{ app()->getLocale() === 'en' ? 'install-language-option-active' : '' }}">
                                        {{ __('install.language.english') }}
                                    </button>
                                    <button type="submit" name="locale" value="fr" class="install-language-option {{ app()->getLocale() === 'fr' ? 'install-language-option-active' : '' }}">
                                        {{ __('install.language.french') }}
                                    </button>
                                </form>
                            </div>

                            <h1 class="mt-4 max-w-lg text-[1.8rem] font-semibold leading-[1.06] text-white sm:text-[2.35rem]">
                                {{ __('install.title') }}
                            </h1>
                            <p class="mt-3 max-w-md text-sm leading-6 text-white/68 sm:text-[0.98rem] sm:leading-7">
                                {{ __('install.subtitle') }}
                            </p>
                        </div>

                        <nav class="mt-5 grid gap-2 sm:grid-cols-3 lg:grid-cols-1" aria-label="{{ __('install.steps_label') }}">
                            @foreach ($steps as $index => $step)
                                <div class="install-step {{ $activeStep === $step ? 'install-step-active' : '' }} flex items-center gap-3 rounded-2xl px-3 py-2">
                                    <span class="install-step-dot flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold">
                                        {{ $index + 1 }}
                                    </span>
                                    <span class="min-w-0 text-sm font-semibold sm:text-[0.95rem]">
                                        {{ __('install.steps.'.$step) }}
                                    </span>
                                </div>
                            @endforeach
                        </nav>
                    </aside>

                    @yield('content')
                </section>

                <footer class="w-full px-2 pb-1 pt-1 sm:px-3 lg:px-1">
                    <div class="grid gap-1 border-t border-white/10 pt-3 text-xs leading-5 text-white/48 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-6">
                        <p class="whitespace-nowrap font-medium text-white/60">{{ __('install.footer.version', ['version' => config('shelfvault.version')]) }}</p>
                        <p class="whitespace-nowrap sm:justify-self-end">{{ __('install.footer.tagline') }}</p>
                    </div>
                </footer>
            </div>
        </main>
    </body>
</html>
