@extends('admin.layout')

@section('title', __('admin.loans.page_title'))

@section('content')
    @php
        $statusTone = static fn (string $status): string => match ($status) {
            'returned' => 'emerald',
            'overdue' => 'rose',
            default => 'sky',
        };

        $typeTone = static fn (?string $type): string => match ($type) {
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
                        {{ __('admin.loans.title') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-600">
                        {{ __('admin.loans.subtitle') }}
                    </p>
                </div>

                <a href="{{ route('admin.collection.index') }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                    {{ __('admin.loans.back_to_collection') }}
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

        @if ($errors->any())
            <div class="rounded-[22px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                <p class="font-semibold">{{ __('admin.loans.validation.heading') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(20rem,0.78fr)_minmax(0,1.22fr)]">
            <section class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-xl border border-zinc-200/80 bg-zinc-50 text-zinc-700 admin-icon-badge-violet">
                        @include('admin.icon', ['name' => 'loans', 'class' => 'h-4 w-4'])
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                            {{ __('admin.loans.new_heading') }}
                        </p>
                        <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                            {{ __('admin.loans.new_title') }}
                        </h2>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.loans.store') }}" class="mt-5 space-y-4">
                    @csrf

                    <label class="block space-y-2">
                        <span class="text-sm font-semibold text-zinc-700">{{ __('admin.loans.fields.item') }}</span>
                        <select name="item_id" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                            <option value="">{{ __('admin.loans.placeholders.choose_item') }}</option>
                            @foreach ($loanableItems as $item)
                                <option value="{{ $item->id }}" @selected((string) old('item_id', $selectedItemId) === (string) $item->id)>
                                    {{ $item->title }} - {{ __('admin.collection.types.'.$item->type->value) }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block space-y-2">
                        <span class="text-sm font-semibold text-zinc-700">{{ __('admin.loans.fields.borrower_name') }}</span>
                        <input type="text" name="borrower_name" value="{{ old('borrower_name') }}" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.loans.fields.loaned_at') }}</span>
                            <input type="date" name="loaned_at" value="{{ old('loaned_at', now()->toDateString()) }}" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.loans.fields.expected_return_at') }}</span>
                            <input type="date" name="expected_return_at" value="{{ old('expected_return_at') }}" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">
                        </label>
                    </div>

                    <label class="block space-y-2">
                        <span class="text-sm font-semibold text-zinc-700">{{ __('admin.loans.fields.notes') }}</span>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100">{{ old('notes') }}</textarea>
                    </label>

                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-full bg-zinc-950 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800">
                        {{ __('admin.loans.actions.create') }}
                    </button>
                </form>
            </section>

            <section class="space-y-6">
                <section class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                                {{ __('admin.loans.active_heading') }}
                            </p>
                            <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                                {{ __('admin.loans.active_title') }}
                            </h2>
                        </div>
                        <span class="admin-stat-chip admin-stat-chip-sky">{{ $activeLoans->count() }}</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($activeLoans as $loan)
                            @include('admin.loans.partials.loan-card', ['loan' => $loan, 'statusTone' => $statusTone, 'typeTone' => $typeTone, 'showReturnAction' => true])
                        @empty
                            <p class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                                {{ __('admin.loans.empty.active') }}
                            </p>
                        @endforelse
                    </div>
                </section>

                <section class="admin-panel !rounded-[20px] !p-4 sm:!p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                                {{ __('admin.loans.history_heading') }}
                            </p>
                            <h2 class="mt-1 text-base font-semibold tracking-tight text-zinc-950">
                                {{ __('admin.loans.history_title') }}
                            </h2>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($returnedLoans as $loan)
                            @include('admin.loans.partials.loan-card', ['loan' => $loan, 'statusTone' => $statusTone, 'typeTone' => $typeTone, 'showReturnAction' => false])
                        @empty
                            <p class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                                {{ __('admin.loans.empty.history') }}
                            </p>
                        @endforelse
                    </div>
                </section>
            </section>
        </section>
    </div>
@endsection
