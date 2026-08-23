@extends('admin.layout')

@section('title', __('admin.settings.page_title'))

@section('content')
    <div class="space-y-6">
        <section class="admin-topbar">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h1 class="text-[1.95rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.4rem]">
                        {{ __('admin.settings.title') }}
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-zinc-600">
                        {{ __('admin.settings.subtitle') }}
                    </p>
                </div>

                <a href="{{ route('admin') }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                    {{ __('admin.collection.back_to_dashboard') }}
                </a>
            </div>
        </section>

        @if (session('status'))
            <div
                x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, 4000)"
                x-show="visible"
                x-transition.opacity.duration.250ms
                x-cloak
                role="status"
                aria-live="polite"
                class="rounded-[22px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ session('status') }}
            </div>
        @endif

        @if (session('settings_error'))
            <div
                x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, 6000)"
                x-show="visible"
                x-transition.opacity.duration.250ms
                x-cloak
                role="alert"
                aria-live="polite"
                class="rounded-[22px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            >
                {{ session('settings_error') }}
            </div>
        @endif

        <section class="space-y-4">
            <form method="POST" action="{{ route('admin.settings.library.update') }}" class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                @csrf
                @method('PUT')

                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.78fr)]">
                    <section class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.settings.library_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.settings.library_title') }}
                        </h2>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(14rem,0.75fr)_minmax(0,1.25fr)]">
                            <label class="block min-w-0 space-y-1">
                                <span class="block text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.settings.library.name_label') }}</span>
                                <input
                                    type="text"
                                    name="library_name"
                                    value="{{ old('library_name', $libraryName) }}"
                                    maxlength="80"
                                    class="h-10 w-full min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm font-medium text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100"
                                >
                                @error('library_name')
                                    <span class="block text-xs font-medium text-rose-700">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="min-w-0 space-y-2">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.settings.library.types_title') }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($contentTypes as $type)
                                        <label class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white/85 px-3 py-2">
                                            <span class="truncate text-sm font-semibold text-zinc-800">{{ $type['label'] }}</span>
                                            <input
                                                type="checkbox"
                                                name="enabled_types[]"
                                                value="{{ $type['value'] }}"
                                                @checked(in_array($type['value'], old('enabled_types', array_values(array_filter(array_map(fn ($entry) => $entry['enabled'] ? $entry['value'] : null, $contentTypes)))), true))
                                                class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500"
                                            >
                                        </label>
                                    @endforeach
                                </div>
                                @error('enabled_types')
                                    <span class="block text-xs font-medium text-rose-700">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.settings.features_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.settings.features_title') }}
                        </h2>

                        <div class="mt-4 rounded-xl border border-zinc-200 bg-white/85 px-3 py-3">
                            <label class="flex items-start justify-between gap-4">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-zinc-900">{{ __('admin.settings.features.loans_title') }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-zinc-500">{{ __('admin.settings.features.loans_help') }}</span>
                                </span>
                                <input type="hidden" name="loans_enabled" value="0">
                                <input
                                    type="checkbox"
                                    name="loans_enabled"
                                    value="1"
                                    @checked((bool) old('loans_enabled', $loansEnabled))
                                    class="mt-1 h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500"
                                >
                            </label>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-full bg-zinc-950 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800 sm:w-auto">
                                {{ __('admin.settings.save') }}
                            </button>
                        </div>
                    </section>
                </div>
            </form>

            <section class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.settings.language_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.settings.language_title') }}
                        </h2>
                        <p class="mt-1 max-w-2xl text-xs leading-5 text-zinc-500">
                            {{ __('admin.settings.language_help') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.update') }}" class="w-full lg:max-w-xl">
                        @csrf

                        <div class="grid gap-2 sm:grid-cols-[minmax(12rem,1fr)_auto] sm:items-end">
                            <label class="min-w-0 space-y-1">
                                <span class="block text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.settings.language_field') }}</span>
                                <select name="preferred_locale" class="h-10 w-full min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm font-medium text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                                    @foreach ($locales as $code => $label)
                                        <option value="{{ $code }}" @selected(old('preferred_locale', $currentLocale) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-full bg-zinc-950 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800 sm:w-auto">
                                {{ __('admin.settings.save') }}
                            </button>
                        </div>

                        @error('preferred_locale')
                            <p class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </form>
                </div>
            </section>

            <section class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.settings.integrations_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.settings.integrations_title') }}
                        </h2>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ($integrations as $integration)
                        @php
                            $stateKey = $integration['state'];
                            $stateTone = match ($stateKey) {
                                'configured' => 'emerald',
                                'pending' => 'amber',
                                default => 'slate',
                            };
                        @endphp

                        <article class="flex min-h-full flex-col rounded-2xl border border-zinc-200/80 bg-white/85 p-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg border border-zinc-200/80 bg-zinc-50 text-zinc-700 admin-icon-badge-{{ $integration['tone'] }}">
                                    @include('admin.icon', ['name' => $integration['icon'], 'class' => 'h-4 w-4 flex-none'])
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="min-w-0 text-sm font-semibold text-zinc-950">
                                            {{ __('admin.settings.integrations.'.$integration['key'].'.title') }}
                                        </h2>
                                        <span class="inline-flex h-5 items-center rounded-full px-2 text-[0.65rem] font-bold admin-stat-chip-{{ $stateTone }}">
                                            {{ __('admin.settings.states.'.$stateKey) }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs leading-5 text-zinc-600">
                                        {{ __('admin.settings.integrations.'.$integration['key'].'.description') }}
                                    </p>
                                </div>
                            </div>

                            @if (! ($integration['optional_future'] ?? false))
                                <form method="POST" action="{{ route('admin.settings.external-services.update', $integration['key']) }}" class="mt-4 flex flex-1 flex-col">
                                    @csrf
                                    @method('PUT')

                                    <div class="grid gap-2 {{ $integration['key'] === 'tmdb' ? 'sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_5.75rem_4.5rem]' : 'sm:grid-cols-2' }}">
                                        @foreach ($integration['fields'] as $field)
                                            @php
                                                $isCompactField = $integration['key'] === 'tmdb' && in_array($field['key'], ['language', 'region'], true);
                                            @endphp

                                            <label class="block min-w-0 space-y-1 {{ $isCompactField ? '' : ($integration['key'] === 'tmdb' ? 'sm:col-span-2 lg:col-span-1' : '') }}">
                                                <span class="block truncate text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ $field['label'] }}</span>

                                                @if (($field['type'] ?? null) === 'select')
                                                    <select name="settings[{{ $field['key'] }}]" class="h-9 w-full min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-sm font-medium text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                                                        <option value="">{{ __('admin.settings.translation_providers.none') }}</option>
                                                        <option value="google" @selected(old('settings.'.$field['key'], $field['value']) === 'google')>{{ __('admin.settings.translation_providers.google') }}</option>
                                                    </select>
                                                @else
                                                    <input
                                                        type="{{ $field['secret'] ? 'password' : 'text' }}"
                                                        name="settings[{{ $field['key'] }}]"
                                                        value="{{ $field['secret'] ? '' : old('settings.'.$field['key'], $field['value']) }}"
                                                        placeholder="{{ $field['secret'] && $field['configured'] ? __('admin.settings.secret_configured_placeholder') : $field['placeholder'] }}"
                                                        autocomplete="off"
                                                        class="h-9 w-full min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-sm font-medium text-zinc-950 outline-none transition placeholder:text-zinc-400 focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100 {{ $isCompactField ? 'text-center' : '' }}"
                                                    >
                                                @endif

                                                @error('settings.'.$field['key'])
                                                    <span class="block text-xs font-medium text-rose-700">{{ $message }}</span>
                                                @enderror
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="mt-auto flex flex-wrap items-center justify-end gap-2 pt-4">
                                        @if ($integration['testable'])
                                            <button
                                                type="submit"
                                                form="test-{{ $integration['key'] }}"
                                                class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950"
                                            >
                                                {{ __('admin.settings.test_connection') }}
                                            </button>
                                        @endif

                                        <button type="submit" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-full bg-zinc-950 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800">
                                            {{ __('admin.settings.save') }}
                                        </button>
                                    </div>
                                </form>

                                @if ($integration['testable'])
                                    <form id="test-{{ $integration['key'] }}" method="POST" action="{{ route('admin.settings.external-services.test', $integration['key']) }}" class="hidden">
                                        @csrf
                                    </form>
                                @endif
                            @else
                                <p class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs leading-5 text-zinc-600">
                                    {{ __('admin.settings.future_provider_help') }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        </section>
    </div>
@endsection
