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

        <section class="grid items-start gap-4 xl:grid-cols-[minmax(24rem,0.85fr)_minmax(0,1.15fr)]">
            <section class="admin-panel self-start !rounded-[20px] !p-4 sm:!p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.settings.language_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.settings.language_title') }}
                        </h2>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-4 space-y-3">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_auto] sm:items-end">
                        <label class="block space-y-1">
                            <span class="block whitespace-nowrap text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.settings.language_field') }}</span>
                            <select name="preferred_locale" class="h-9 w-full rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-sm font-medium text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                                @foreach ($locales as $code => $label)
                                    <option value="{{ $code }}" @selected(old('preferred_locale', $currentLocale) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <button type="submit" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-full bg-zinc-950 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800">
                            {{ __('admin.settings.save') }}
                        </button>
                    </div>

                    @error('preferred_locale')
                        <p class="text-sm font-medium text-rose-700">{{ $message }}</p>
                    @enderror

                    <p class="text-xs leading-4 text-zinc-500">
                        {{ __('admin.settings.language_help') }}
                    </p>
                </form>
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

                <div class="mt-3 divide-y divide-zinc-200/80 overflow-hidden rounded-2xl border border-zinc-200/80 bg-white/70">
                    @foreach ($integrations as $integration)
                        @php
                            $stateKey = $integration['configured'] ? 'configured' : (($integration['optional_future'] ?? false) ? 'optional_future' : 'missing');
                            $stateTone = $integration['configured'] ? 'emerald' : 'slate';
                        @endphp

                        <article class="px-3 py-2.5">
                            <div class="flex min-w-0 items-start gap-2.5">
                                <span class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-lg border border-zinc-200/80 bg-zinc-50 text-zinc-700 admin-icon-badge-{{ $integration['tone'] }}">
                                    @include('admin.icon', ['name' => $integration['icon'], 'class' => 'h-3.5 w-3.5 flex-none'])
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="grid gap-2 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <h2 class="min-w-0 text-sm font-semibold text-zinc-950">
                                                    {{ __('admin.settings.integrations.'.$integration['key'].'.title') }}
                                                </h2>
                                                <span class="inline-flex h-5 items-center rounded-full px-2 text-[0.65rem] font-bold admin-stat-chip-{{ $stateTone }}">
                                                    {{ __('admin.settings.states.'.$stateKey) }}
                                                </span>
                                            </div>

                                            <p class="mt-0.5 text-xs leading-4 text-zinc-600">
                                                {{ __('admin.settings.integrations.'.$integration['key'].'.description') }}
                                            </p>
                                        </div>

                                        <div class="flex min-w-0 flex-wrap gap-1.5 lg:max-w-xs lg:justify-end">
                                            @foreach ($integration['variables'] as $variable)
                                                <code class="max-w-full whitespace-nowrap rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[0.66rem] font-semibold text-zinc-600">{{ $variable }}</code>
                                            @endforeach
                                        </div>
                                    </div>

                                    @if ($integration['key'] === 'translation')
                                        <dl class="mt-2 grid gap-1.5 text-xs text-zinc-600 sm:grid-cols-2">
                                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1">
                                                <dt class="font-medium text-zinc-700">{{ __('admin.settings.provider_label') }}</dt>
                                                <dd class="text-right">{{ $integration['provider'] }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1">
                                                <dt class="font-medium text-zinc-700">{{ __('admin.settings.api_key_label') }}</dt>
                                                <dd class="text-right">
                                                    {{ __('admin.settings.states.'.($integration['api_key_configured'] ? 'configured' : 'missing')) }}
                                                </dd>
                                            </div>
                                        </dl>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </section>
    </div>
@endsection
