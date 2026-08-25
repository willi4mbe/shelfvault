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
    </div>
@endsection
