@extends('admin.layout')

@section('title', __('admin.collection.page_title'))

@section('content')
    @php
        $typeChip = static fn (string $value): string => match ($value) {
            'film' => 'amber',
            'tv_series' => 'sky',
            'video_game' => 'emerald',
            'board_game' => 'violet',
            default => 'slate',
        };
    @endphp

    <div class="space-y-6">
        <section class="admin-topbar">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <h1 class="text-[1.95rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.4rem]">
                        {{ __('admin.collection.title') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-600">
                        {{ __('admin.collection.subtitle') }}
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('admin') }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                        {{ __('admin.collection.back_to_dashboard') }}
                    </a>
                    <a href="{{ route('admin.collection.create') }}" class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800">
                        {{ __('admin.collection.add_item') }}
                    </a>
                </div>
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

        <section class="admin-panel space-y-4">
            <form
                method="GET"
                action="{{ route('admin.collection.index') }}"
                x-data="{ timer: null, submitNow() { this.$refs.form.requestSubmit(); }, queueSubmit() { clearTimeout(this.timer); this.timer = setTimeout(() => this.submitNow(), 300); } }"
                x-ref="form"
                class="grid gap-3 lg:grid-cols-[minmax(0,1.45fr)_minmax(12rem,0.7fr)_auto]"
            >
                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.filters.search') }}</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $filters['search'] }}"
                        placeholder="{{ __('admin.collection.filters.search_placeholder') }}"
                        x-on:input="queueSubmit()"
                        x-on:keydown.enter.prevent="submitNow()"
                        class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100"
                    >
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.filters.type') }}</span>
                    <select name="type" x-on:change="submitNow()" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                        <option value="">{{ __('admin.collection.placeholders.all_types') }}</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-3 lg:justify-end">
                    <a href="{{ route('admin.collection.index') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-zinc-300 bg-white px-4 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                        {{ __('admin.collection.filters.reset') }}
                    </a>
                </div>
            </form>

        </section>

        @if ($items->isEmpty())
            <section class="admin-empty-state">
                <span class="admin-icon-badge admin-icon-badge-sky admin-empty-state-icon">
                    @include('admin.icon', ['name' => 'collection', 'class' => 'admin-icon'])
                </span>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-zinc-950">
                            {{ $filters['search'] || $filters['type'] ? __('admin.collection.empty.filtered_title') : __('admin.collection.empty.title') }}
                        </p>
                        <p class="mt-1 text-sm leading-6 text-zinc-600">
                            {{ $filters['search'] || $filters['type'] ? __('admin.collection.empty.filtered_text') : __('admin.collection.empty.text') }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if ($filters['search'] || $filters['type'])
                            <a href="{{ route('admin.collection.index') }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                                {{ __('admin.collection.filters.reset') }}
                            </a>
                        @endif
                        <a href="{{ route('admin.collection.create') }}" class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800">
                            {{ __('admin.collection.empty.action') }}
                        </a>
                    </div>
                </div>
            </section>
        @else
            <section class="admin-panel space-y-5">
                <div class="hidden overflow-hidden rounded-[22px] border border-zinc-200 bg-white/90 md:block">
                    <div class="overflow-x-auto">
                                <table class="min-w-full table-fixed border-separate border-spacing-0">
                        <thead class="bg-zinc-50">
                            <tr class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">
                                <th class="w-[31%] px-4 py-3 text-left">{{ __('admin.collection.table.title') }}</th>
                                <th class="w-[10%] px-4 py-3 text-left">{{ __('admin.collection.table.type') }}</th>
                                <th class="w-[13%] px-4 py-3 text-left">{{ __('admin.collection.table.format') }}</th>
                                <th class="w-[12%] px-4 py-3 text-left">{{ __('admin.collection.table.condition') }}</th>
                                <th class="w-[14%] px-4 py-3 text-left">{{ __('admin.collection.table.location') }}</th>
                                <th class="w-[23%] px-4 py-3 text-left">{{ __('admin.collection.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($items as $item)
                                <tr class="group bg-white transition hover:bg-zinc-50/80">
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <a href="{{ route('admin.collection.edit', $item) }}" class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-700 focus:outline-none focus:ring-4 focus:ring-zinc-100" aria-label="{{ $item->title }}">
                                                @if ($item->coverUrl())
                                                    <img src="{{ $item->coverUrl() }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                                                @else
                                                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-4 w-4'])
                                                    <span class="sr-only">{{ __('admin.collection.placeholders.no_cover') }}</span>
                                                @endif
                                            </a>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.collection.edit', $item) }}" class="admin-collection-title-clamp block text-sm font-semibold leading-5 text-zinc-950 transition group-hover:text-zinc-700 focus:outline-none focus:underline">
                                                    {{ $item->title }}
                                                </a>
                                                <p class="mt-1 text-xs text-zinc-500">
                                                    @if ($item->barcode)
                                                        {{ __('admin.collection.table.barcode') }}: {{ $item->barcode }}
                                                    @else
                                                        {{ __('admin.collection.table.no_barcode') }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span class="admin-stat-chip admin-stat-chip-{{ $typeChip($item->type->value) }}">
                                            {{ __('admin.collection.types.'.$item->type->value) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-zinc-700">
                                        {{ $item->physical_format ? $formatLabels[$item->physical_format] ?? $item->physical_format : '—' }}
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-zinc-700">
                                        {{ $item->physical_format === 'digital_copy' ? '—' : ($item->condition ? $conditionLabels[$item->condition->value] ?? $item->condition->value : __('admin.collection.placeholders.not_specified')) }}
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-zinc-700">
                                        {{ $item->location ?: '—' }}
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex justify-start gap-2 whitespace-nowrap pl-1 md:pl-2">
                                            @if ($loansEnabled && ! $item->has_active_loan)
                                                <a
                                                    href="{{ route('admin.loans.index', ['item' => $item->id]) }}"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 focus:outline-none focus:ring-4 focus:ring-zinc-100"
                                                    aria-label="{{ __('admin.loans.actions.create') }}"
                                                    title="{{ __('admin.loans.actions.create') }}"
                                                >
                                                    @include('admin.icon', ['name' => 'loans', 'class' => 'h-4 w-4'])
                                                    <span class="sr-only">{{ __('admin.loans.actions.create') }}</span>
                                                </a>
                                            @endif
                                            <a
                                                href="{{ route('admin.collection.edit', $item) }}"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 focus:outline-none focus:ring-4 focus:ring-zinc-100"
                                                aria-label="{{ __('admin.collection.actions.edit') }}"
                                                title="{{ __('admin.collection.actions.edit') }}"
                                            >
                                                @include('admin.icon', ['name' => 'edit', 'class' => 'h-4 w-4'])
                                                <span class="sr-only">{{ __('admin.collection.actions.edit') }}</span>
                                            </a>
                                            <form method="POST" action="{{ route('admin.collection.destroy', $item) }}" onsubmit="return confirm(@js(__('admin.collection.actions.confirm_delete')));">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                                    aria-label="{{ __('admin.collection.actions.delete') }}"
                                                    title="{{ __('admin.collection.actions.delete') }}"
                                                >
                                                    @include('admin.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                                                    <span class="sr-only">{{ __('admin.collection.actions.delete') }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($items as $item)
                                <article class="rounded-[22px] border border-zinc-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <a href="{{ route('admin.collection.edit', $item) }}" class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-700 focus:outline-none focus:ring-4 focus:ring-zinc-100" aria-label="{{ $item->title }}">
                                                @if ($item->coverUrl())
                                                    <img src="{{ $item->coverUrl() }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                                                @else
                                                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-4 w-4'])
                                                    <span class="sr-only">{{ __('admin.collection.placeholders.no_cover') }}</span>
                                                @endif
                                            </a>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.collection.edit', $item) }}" class="admin-collection-title-clamp block text-base font-semibold leading-5 text-zinc-950 transition hover:text-zinc-700 focus:outline-none focus:underline">
                                                    {{ $item->title }}
                                                </a>
                                                <p class="mt-1 text-sm text-zinc-500">
                                                    {{ __('admin.collection.types.'.$item->type->value) }}
                                                </p>
                                            </div>
                                        </div>
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.collection.table.format') }}</dt>
                                    <dd class="mt-1 text-zinc-700">{{ $item->physical_format ? $formatLabels[$item->physical_format] ?? $item->physical_format : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.collection.table.condition') }}</dt>
                                    <dd class="mt-1 text-zinc-700">{{ $item->physical_format === 'digital_copy' ? '—' : ($item->condition ? $conditionLabels[$item->condition->value] ?? $item->condition->value : __('admin.collection.placeholders.not_specified')) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.collection.table.location') }}</dt>
                                    <dd class="mt-1 text-zinc-700">{{ $item->location ?: '—' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm text-zinc-500">
                                    {{ $item->barcode ? __('admin.collection.table.barcode').': '.$item->barcode : __('admin.collection.table.no_barcode') }}
                                </p>
                                <div class="flex items-center gap-2">
                                    @if ($loansEnabled && ! $item->has_active_loan)
                                        <a
                                            href="{{ route('admin.loans.index', ['item' => $item->id]) }}"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 focus:outline-none focus:ring-4 focus:ring-zinc-100"
                                            aria-label="{{ __('admin.loans.actions.create') }}"
                                            title="{{ __('admin.loans.actions.create') }}"
                                        >
                                            @include('admin.icon', ['name' => 'loans', 'class' => 'h-4 w-4'])
                                            <span class="sr-only">{{ __('admin.loans.actions.create') }}</span>
                                        </a>
                                    @endif
                                    <a
                                        href="{{ route('admin.collection.edit', $item) }}"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 focus:outline-none focus:ring-4 focus:ring-zinc-100"
                                        aria-label="{{ __('admin.collection.actions.edit') }}"
                                        title="{{ __('admin.collection.actions.edit') }}"
                                    >
                                        @include('admin.icon', ['name' => 'edit', 'class' => 'h-4 w-4'])
                                        <span class="sr-only">{{ __('admin.collection.actions.edit') }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.collection.destroy', $item) }}" onsubmit="return confirm(@js(__('admin.collection.actions.confirm_delete')));">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                            aria-label="{{ __('admin.collection.actions.delete') }}"
                                            title="{{ __('admin.collection.actions.delete') }}"
                                        >
                                            @include('admin.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                                            <span class="sr-only">{{ __('admin.collection.actions.delete') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
