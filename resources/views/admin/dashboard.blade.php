@extends('admin.layout')

@section('title', __('admin.dashboard.page_title'))

@section('content')
    <div class="space-y-6">
        <section class="admin-topbar">
            <div class="max-w-3xl">
                <h1 class="text-[1.95rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.4rem]">
                    {{ __('admin.dashboard.title') }}
                </h1>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                        {{ __('admin.dashboard.stats_heading') }}
                    </p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-zinc-950">
                        {{ __('admin.dashboard.stats_title') }}
                    </h2>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($stats as $stat)
                    <article @class(['admin-stat-card', 'admin-stat-card-'.$stat['tone']])>
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-3">
                                <span class="admin-icon-badge admin-icon-badge-{{ $stat['tone'] }}">
                                    @include('admin.icon', ['name' => $stat['icon'], 'class' => 'admin-icon'])
                                </span>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">
                                        {{ __('admin.dashboard.stats.'.$stat['key'].'.label') }}
                                    </p>
                                    <p class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950">
                                        {{ number_format($stat['value']) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-zinc-600">
                            {{ __('admin.dashboard.stats.'.$stat['key'].'.hint') }}
                        </p>
                    </article>
                @endforeach
            </div>
        </section>

        <x-admin.block
            :heading="__('admin.dashboard.quick_access_heading')"
            :title="__('admin.dashboard.quick_access_title')"
        >
            <div class="mt-5 grid gap-3 md:grid-cols-3">
                @foreach ($quickAccess as $item)
                    <article class="admin-quick-card">
                        <div class="flex items-start justify-between gap-3">
                            <span class="admin-icon-badge admin-icon-badge-{{ $item['tone'] }}">
                                @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-icon'])
                            </span>

                            @if (($item['soon'] ?? false))
                                <span class="admin-stat-chip admin-stat-chip-slate">{{ __('admin.dashboard.soon') }}</span>
                            @endif
                        </div>

                        <p class="mt-4 text-sm font-semibold text-zinc-950">
                            {{ __('admin.navigation.'.$item['key']) }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-zinc-600">
                            {{ __('admin.dashboard.quick_access.'.$item['note']) }}
                        </p>
                    </article>
                @endforeach
            </div>
        </x-admin.block>

        <x-admin.block
            :heading="__('admin.dashboard.overview_heading')"
            :title="__('admin.dashboard.overview_title')"
        >
            <div class="mt-5 space-y-3">
                @foreach ($overview as $item)
                    <div class="admin-list-row">
                        <div class="flex items-start gap-3">
                            <span class="admin-icon-badge admin-icon-badge-{{ $item['tone'] }}">
                                @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-icon'])
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-zinc-950">
                                    {{ __('admin.dashboard.overview.'.$item['key'].'.title') }}
                                </p>
                                <p class="mt-1 text-sm leading-6 text-zinc-600">
                                    {{ __('admin.dashboard.overview.'.$item['key'].'.detail') }}
                                </p>
                            </div>
                        </div>
                        <span class="admin-overview-value">
                            {{ $item['value'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-admin.block>

        <section class="grid gap-4 xl:grid-cols-[0.94fr_1.06fr]">
            <x-admin.block
                :heading="__('admin.dashboard.activity_heading')"
                :title="__('admin.dashboard.activity_title')"
            >
                <div class="mt-5">
                    <div class="admin-empty-state">
                        <span class="admin-icon-badge admin-icon-badge-sky admin-empty-state-icon">
                            @include('admin.icon', ['name' => 'recent_additions', 'class' => 'admin-icon'])
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-zinc-950">
                                {{ __('admin.dashboard.activity_empty') }}
                            </p>
                        </div>
                    </div>
                </div>
            </x-admin.block>

            <x-admin.block
                :heading="__('admin.dashboard.setup_heading')"
                :title="__('admin.dashboard.setup_title')"
            >
                <div class="mt-5 space-y-3">
                    @foreach ($setupStatus as $item)
                        @php
                            $stateTone = $item['value'] === 'ready' ? 'emerald' : 'amber';
                        @endphp
                        <div class="admin-list-row">
                            <div class="flex items-start gap-3">
                                <span class="admin-icon-badge admin-icon-badge-{{ $item['tone'] }}">
                                    @include('admin.icon', ['name' => $item['icon'], 'class' => 'admin-icon'])
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-950">
                                        {{ __('admin.dashboard.setup.'.$item['key'].'.title') }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-zinc-600">
                                        {{ __('admin.dashboard.setup.'.$item['key'].'.detail') }}
                                    </p>
                                </div>
                            </div>
                            <span class="admin-stat-chip admin-stat-chip-{{ $stateTone }}">
                                {{ __('admin.dashboard.setup.'.$item['key'].'.state') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-admin.block>
        </section>
    </div>
@endsection
