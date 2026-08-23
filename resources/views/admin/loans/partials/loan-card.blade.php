@php
    $item = $loan->item;
    $status = $loan->statusKey();
    $coverUrl = $item?->coverUrl();
@endphp

<article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <a href="{{ $item ? route('admin.collection.show', $item) : '#' }}" class="flex h-16 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 text-zinc-500">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                @else
                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-4 w-4'])
                @endif
            </a>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ $item ? route('admin.collection.show', $item) : '#' }}" class="text-sm font-semibold leading-5 text-zinc-950 transition hover:text-zinc-700">
                        {{ $item?->title ?? __('admin.loans.item_missing') }}
                    </a>
                    <span class="admin-stat-chip admin-stat-chip-{{ $statusTone($status) }}">
                        {{ __('admin.loans.statuses.'.$status) }}
                    </span>
                    @if ($item?->type)
                        <span class="admin-stat-chip admin-stat-chip-{{ $typeTone($item->type->value) }}">
                            {{ __('admin.collection.types.'.$item->type->value) }}
                        </span>
                    @endif
                </div>

                <dl @class([
                    'mt-3 grid gap-2 text-sm text-zinc-600',
                    'sm:grid-cols-3' => $showReturnAction,
                    'sm:grid-cols-4' => ! $showReturnAction,
                ])>
                    <div>
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.loans.fields.borrower_name') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-800">{{ $loan->borrower_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.loans.fields.loaned_at') }}</dt>
                        <dd class="mt-1">{{ optional($loan->loaned_at)->format('Y-m-d') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.loans.fields.expected_return_at') }}</dt>
                        <dd class="mt-1">{{ optional($loan->expected_return_at)->format('Y-m-d') ?: __('admin.loans.placeholders.no_expected_return') }}</dd>
                    </div>
                    @unless ($showReturnAction)
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ __('admin.loans.fields.returned_at') }}</dt>
                            <dd class="mt-1 font-medium text-zinc-800">{{ optional($loan->returned_at)->format('Y-m-d') }}</dd>
                        </div>
                    @endunless
                </dl>

                @if (filled($loan->notes))
                    <p class="mt-3 text-sm leading-6 text-zinc-600">{{ $loan->notes }}</p>
                @endif
            </div>
        </div>

        @if ($showReturnAction)
            <form method="POST" action="{{ route('admin.loans.return', $loan) }}" class="sm:flex-none">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 sm:w-auto">
                    {{ __('admin.loans.actions.mark_returned') }}
                </button>
            </form>
        @endif
    </div>
</article>
