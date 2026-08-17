@extends('admin.layout')

@section('title', __('admin.settings.page_title'))

@section('content')
    <div class="space-y-6">
        <section class="admin-topbar">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold tracking-[0.16em] text-zinc-500 uppercase">
                        {{ __('admin.settings.kicker') }}
                    </p>
                    <h1 class="mt-3 text-[1.95rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.4rem]">
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

        <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <x-admin.block
                :heading="__('admin.settings.integrations_heading')"
                :title="__('admin.settings.integrations_title')"
            >
                <div class="mt-5 space-y-3">
                    @foreach ($integrations as $integration)
                        @php
                            $stateKey = $integration['configured'] ? 'configured' : (($integration['planned'] ?? false) ? 'planned' : 'missing');
                            $stateTone = $integration['configured'] ? 'emerald' : (($integration['planned'] ?? false) ? 'amber' : 'slate');
                        @endphp

                        <article class="admin-list-row items-start">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="admin-icon-badge admin-icon-badge-{{ $integration['tone'] }}">
                                    @include('admin.icon', ['name' => $integration['icon'], 'class' => 'admin-icon'])
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-zinc-950">
                                            {{ __('admin.settings.integrations.'.$integration['key'].'.title') }}
                                        </h2>
                                        <span class="admin-stat-chip admin-stat-chip-{{ $stateTone }}">
                                            {{ __('admin.settings.states.'.$stateKey) }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm leading-6 text-zinc-600">
                                        {{ __('admin.settings.integrations.'.$integration['key'].'.description') }}
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($integration['variables'] as $variable)
                                            <code class="admin-meta-pill">{{ $variable }}</code>
                                        @endforeach
                                    </div>

                                    @if ($integration['key'] === 'translation')
                                        <dl class="mt-3 grid gap-2 text-sm text-zinc-600 sm:grid-cols-2">
                                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2">
                                                <dt class="font-medium text-zinc-700">{{ __('admin.settings.provider_label') }}</dt>
                                                <dd class="text-right">{{ $integration['provider'] }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2">
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
            </x-admin.block>

            <div class="space-y-4">
                <x-admin.block
                    :heading="__('admin.settings.security_heading')"
                    :title="__('admin.settings.security_title')"
                >
                    <div class="mt-5 space-y-3">
                        @foreach (['secrets_hidden', 'optional_integrations', 'env_only'] as $item)
                            <div class="admin-list-row">
                                <div class="flex items-start gap-3">
                                    <span class="admin-icon-badge admin-icon-badge-slate">
                                        @include('admin.icon', ['name' => 'auth', 'class' => 'admin-icon'])
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-950">
                                            {{ __('admin.settings.security.'.$item.'.title') }}
                                        </p>
                                        <p class="mt-1 text-sm leading-6 text-zinc-600">
                                            {{ __('admin.settings.security.'.$item.'.detail') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-admin.block>

                <x-admin.block
                    :heading="__('admin.settings.next_heading')"
                    :title="__('admin.settings.next_title')"
                >
                    <p class="mt-5 text-sm leading-6 text-zinc-600">
                        {{ __('admin.settings.next_text') }}
                    </p>
                </x-admin.block>
            </div>
        </section>
    </div>
@endsection
