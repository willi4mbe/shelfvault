@extends('admin.layout')

@section('title', $item->title.' - '.__('admin.collection.detail.page_title'))

@section('content')
    @php
        $typeValue = $item->type->value;
        $coverUrl = $item->coverUrl();

        $typeConfig = match ($typeValue) {
            'film' => [
                'rgb' => '99 102 241',
                'chip' => 'violet',
            ],
            'tv_series' => [
                'rgb' => '14 165 233',
                'chip' => 'sky',
            ],
            'video_game' => [
                'rgb' => '20 184 166',
                'chip' => 'emerald',
            ],
            'board_game' => [
                'rgb' => '245 158 11',
                'chip' => 'amber',
            ],
            default => [
                'rgb' => '99 102 241',
                'chip' => 'slate',
            ],
        };

        $statusChip = $item->status->value === 'owned'
            ? 'emerald'
            : ($item->status->value === 'loaned' ? 'rose' : 'slate');

        $formatLabel = match ($typeValue) {
            'film' => match ($item->physical_format) {
                'dvd' => __('admin.collection.formats.film.dvd'),
                'blu_ray' => __('admin.collection.formats.film.blu_ray'),
                '4k_uhd' => __('admin.collection.formats.film.four_k_uhd'),
                'vhs' => __('admin.collection.formats.film.vhs'),
                'digital_copy' => __('admin.collection.formats.film.digital_copy'),
                default => $item->physical_format,
            },
            'tv_series' => match ($item->physical_format) {
                'dvd' => __('admin.collection.formats.tv_series.dvd'),
                'blu_ray' => __('admin.collection.formats.tv_series.blu_ray'),
                '4k_uhd' => __('admin.collection.formats.tv_series.four_k_uhd'),
                'box_set' => __('admin.collection.formats.tv_series.box_set'),
                'digital_copy' => __('admin.collection.formats.tv_series.digital_copy'),
                default => $item->physical_format,
            },
            'video_game' => match ($item->physical_format) {
                'cartridge' => __('admin.collection.formats.video_game.cartridge'),
                'disc' => __('admin.collection.formats.video_game.disc'),
                'code_in_box' => __('admin.collection.formats.video_game.code_in_box'),
                'collector_edition' => __('admin.collection.formats.video_game.collector_edition'),
                'digital_copy' => __('admin.collection.formats.video_game.digital_copy'),
                default => $item->physical_format,
            },
            'board_game' => match ($item->physical_format) {
                'box' => __('admin.collection.formats.board_game.box'),
                'expansion' => __('admin.collection.formats.board_game.expansion'),
                'card_game' => __('admin.collection.formats.board_game.card_game'),
                'accessory' => __('admin.collection.formats.board_game.accessory'),
                default => $item->physical_format,
            },
            default => $item->physical_format,
        };

        $mainRows = array_values(array_filter([
            ['label' => __('admin.collection.fields.type'), 'value' => __('admin.collection.types.'.$typeValue)],
            ['label' => __('admin.collection.fields.title'), 'value' => $item->title],
            ['label' => __('admin.collection.fields.original_title'), 'value' => $item->original_title],
            ['label' => __('admin.collection.fields.release_year'), 'value' => $item->release_year],
            ['label' => __('admin.collection.fields.barcode'), 'value' => $item->barcode],
            ['label' => __('admin.collection.fields.condition'), 'value' => $item->condition ? __('admin.collection.conditions.'.$item->condition->value) : __('admin.collection.placeholders.not_specified')],
            ['label' => __('admin.collection.fields.status'), 'value' => __('admin.collection.statuses.'.$item->status->value)],
            ['label' => __('admin.collection.fields.is_favorite'), 'value' => $item->is_favorite ? __('admin.collection.values.yes') : __('admin.collection.values.no')],
        ], static fn (array $row): bool => filled($row['value'])));

        $physicalRows = array_values(array_filter([
            ['label' => __('admin.collection.fields.physical_format'), 'value' => $formatLabel],
            ['label' => __('admin.collection.fields.edition'), 'value' => $item->edition],
            ['label' => __('admin.collection.fields.region'), 'value' => $item->region],
            ['label' => __('admin.collection.fields.location'), 'value' => $item->location],
            ['label' => __('admin.collection.fields.acquired_at'), 'value' => optional($item->acquired_at)->translatedFormat('Y-m-d')],
        ], static fn (array $row): bool => filled($row['value'])));

        $specificRows = match ($typeValue) {
            'film' => array_values(array_filter([
                ['label' => __('admin.collection.fields.external_tmdb_id'), 'value' => $item->external_tmdb_id],
            ], static fn (array $row): bool => filled($row['value']))),
            'tv_series' => array_values(array_filter([
                ['label' => __('admin.collection.fields.season_count'), 'value' => $item->season_count],
                ['label' => __('admin.collection.fields.episode_count'), 'value' => $item->episode_count],
                ['label' => __('admin.collection.fields.end_year'), 'value' => $item->end_year],
                ['label' => __('admin.collection.fields.runtime_minutes'), 'value' => $item->runtime_minutes ? $item->runtime_minutes.' '.__('admin.collection.unit.minutes') : null],
                ['label' => __('admin.collection.fields.showrunner'), 'value' => $item->showrunner],
                ['label' => __('admin.collection.fields.network'), 'value' => $item->network],
                ['label' => __('admin.collection.fields.studio'), 'value' => $item->studio],
                ['label' => __('admin.collection.fields.age_rating'), 'value' => $item->age_rating],
                ['label' => __('admin.collection.fields.genres'), 'value' => is_array($item->genres) && $item->genres !== [] ? implode(', ', $item->genres) : null],
                ['label' => __('admin.collection.fields.external_tmdb_id'), 'value' => $item->external_tmdb_id],
            ], static fn (array $row): bool => filled($row['value']))),
            'video_game' => array_values(array_filter([
                ['label' => __('admin.collection.fields.platform'), 'value' => $item->platform],
                ['label' => __('admin.collection.fields.developer'), 'value' => $item->developer],
                ['label' => __('admin.collection.fields.publisher'), 'value' => $item->publisher],
                ['label' => __('admin.collection.fields.age_rating'), 'value' => $item->age_rating],
                ['label' => __('admin.collection.fields.genres'), 'value' => is_array($item->genres) && $item->genres !== [] ? implode(', ', $item->genres) : null],
                ['label' => __('admin.collection.fields.modes'), 'value' => is_array($item->modes) && $item->modes !== [] ? implode(', ', $item->modes) : null],
            ], static fn (array $row): bool => filled($row['value']))),
            'board_game' => array_values(array_filter([
                ['label' => __('admin.collection.fields.min_players'), 'value' => $item->min_players],
                ['label' => __('admin.collection.fields.max_players'), 'value' => $item->max_players],
                ['label' => __('admin.collection.fields.play_time_minutes'), 'value' => $item->play_time_minutes ? $item->play_time_minutes.' '.__('admin.collection.unit.minutes') : null],
                ['label' => __('admin.collection.fields.designer'), 'value' => $item->designer],
                ['label' => __('admin.collection.fields.publisher'), 'value' => $item->publisher],
                ['label' => __('admin.collection.fields.genres'), 'value' => is_array($item->genres) && $item->genres !== [] ? implode(', ', $item->genres) : null],
            ], static fn (array $row): bool => filled($row['value']))),
            default => [],
        };

        $typeBadgeClass = $typeConfig['chip'];
        $genres = is_array($item->genres) ? array_values(array_filter($item->genres, static fn ($genre): bool => filled($genre))) : [];
        $castMembers = is_array($item->cast_members) ? array_values(array_filter($item->cast_members, static fn ($member): bool => filled($member))) : [];
        $featuredCastMembers = array_slice($castMembers, 0, 5);
        $hiddenCastMembersCount = max(count($castMembers) - count($featuredCastMembers), 0);
        $runtimeLabel = $item->runtime_minutes ? $item->runtime_minutes.' '.__('admin.collection.unit.minutes') : null;
        $filmHighlights = $typeValue === 'film'
            ? array_values(array_filter([
                ['label' => __('admin.collection.fields.director'), 'value' => $item->director],
                ['label' => __('admin.collection.fields.runtime_minutes'), 'value' => $runtimeLabel],
                ['label' => __('admin.collection.fields.age_rating'), 'value' => $item->age_rating],
            ], static fn (array $row): bool => filled($row['value'])))
            : [];
        $seriesHighlights = $typeValue === 'tv_series'
            ? array_values(array_filter([
                ['label' => __('admin.collection.fields.season_count'), 'value' => $item->season_count],
                ['label' => __('admin.collection.fields.episode_count'), 'value' => $item->episode_count],
                ['label' => __('admin.collection.fields.showrunner'), 'value' => $item->showrunner],
            ], static fn (array $row): bool => filled($row['value'])))
            : [];
        $overviewHighlights = $typeValue === 'tv_series' ? $seriesHighlights : $filmHighlights;
    @endphp

    <div class="relative isolate space-y-6" style="--media-accent: {{ $typeConfig['rgb'] }};">
        <div
            class="pointer-events-none absolute inset-x-8 top-0 -z-10 h-44 rounded-full blur-3xl"
            style="background: radial-gradient(circle at center, rgb(var(--media-accent) / 0.18), transparent 70%);"
        ></div>

        <section class="admin-media-hero px-5 py-5 sm:px-6 sm:py-6 lg:p-8">
            <div class="admin-media-hero-inner flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="admin-stat-chip admin-stat-chip-{{ $typeBadgeClass }}">
                            {{ __('admin.collection.types.'.$typeValue) }}
                        </span>
                        <span class="admin-stat-chip admin-stat-chip-{{ $statusChip }}">
                            {{ __('admin.collection.statuses.'.$item->status->value) }}
                        </span>
                        @if ($item->is_favorite)
                            <span class="admin-stat-chip admin-stat-chip-amber">
                                {{ __('admin.collection.fields.is_favorite') }}
                            </span>
                        @endif
                        @if (filled($formatLabel))
                            <span class="admin-stat-chip admin-stat-chip-sky">
                                {{ $formatLabel }}
                            </span>
                        @endif
                        @if ($item->condition)
                            <span class="admin-stat-chip admin-stat-chip-slate">
                                {{ __('admin.collection.conditions.'.$item->condition->value) }}
                            </span>
                        @endif
                    </div>

                    <div class="max-w-3xl space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                            {{ __('admin.collection.detail.kicker') }}
                        </p>
                        <h1 class="text-[clamp(2.35rem,4vw,3.9rem)] font-semibold leading-[0.94] tracking-[-0.05em] text-zinc-950">
                            {{ $item->title }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600">
                            @if ($item->original_title)
                                <span>{{ $item->original_title }}</span>
                            @endif
                            @if ($item->release_year)
                                <span>{{ $item->release_year }}{{ $item->end_year ? ' - '.$item->end_year : '' }}</span>
                            @endif
                            @if ($item->barcode)
                                <span>{{ __('admin.collection.table.barcode') }}: {{ $item->barcode }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="admin-media-actions xl:justify-end">
                    <a href="{{ $backUrl }}" class="admin-media-button admin-media-button-secondary">
                        {{ __('admin.collection.actions.back') }}
                    </a>
                    <a href="{{ route('admin.collection.edit', $item) }}" class="admin-media-button admin-media-button-primary">
                        {{ __('admin.collection.actions.edit') }}
                    </a>
                    <form method="POST" action="{{ route('admin.collection.destroy', $item) }}" onsubmit="return confirm(@js(__('admin.collection.actions.confirm_delete')));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-media-button admin-media-button-danger">
                            {{ __('admin.collection.actions.delete') }}
                        </button>
                    </form>
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

        <section class="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
            <div class="space-y-4">
                <div class="admin-media-cover-shell p-3 sm:p-4">
                    <div class="admin-media-cover-frame">
                        @if ($coverUrl)
                            <img src="{{ $coverUrl }}" alt="{{ $item->title }}" class="admin-media-cover-image">
                        @else
                            <div class="admin-media-cover-placeholder">
                                <span class="admin-media-placeholder-mark">
                                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-6 w-6'])
                                </span>
                                <div class="space-y-2">
                                    <p class="admin-media-cover-placeholder-title">{{ __('admin.collection.placeholders.no_cover') }}</p>
                                    <p class="admin-media-cover-placeholder-text">{{ __('admin.collection.detail.cover_hint') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @if (in_array($typeValue, ['film', 'tv_series'], true) && (filled($item->description) || $genres !== [] || $overviewHighlights !== [] || $featuredCastMembers !== [] || filled($item->studio) || filled($item->network)))
                    <section class="admin-media-section p-5 sm:p-6">
                        <div class="admin-media-section-header">
                            <span class="admin-icon-badge admin-icon-badge-{{ $typeBadgeClass }}">
                                @include('admin.icon', ['name' => 'overview', 'class' => 'admin-icon'])
                            </span>
                            <h2 class="admin-media-section-label">
                                {{ $typeValue === 'tv_series' ? __('admin.collection.detail.sections.tv_series_overview') : __('admin.collection.detail.sections.film_overview') }}
                            </h2>
                        </div>

                        <div class="mt-5 space-y-5">
                            @if ($genres !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($genres as $genre)
                                        <span class="admin-stat-chip admin-stat-chip-sky">{{ $genre }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($overviewHighlights !== [])
                                <dl class="grid gap-3 sm:grid-cols-3">
                                    @foreach ($overviewHighlights as $row)
                                        <div class="admin-media-field">
                                            <div class="admin-media-field-inner">
                                                <dt class="admin-media-field-label">{{ $row['label'] }}</dt>
                                                <dd class="admin-media-field-value">{{ $row['value'] }}</dd>
                                            </div>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @if (filled($item->description))
                                <div class="rounded-[22px] border border-zinc-200/80 bg-white/75 p-4 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                        {{ __('admin.collection.fields.description') }}
                                    </p>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-7 text-zinc-700">
                                        {{ $item->description }}
                                    </p>
                                </div>
                            @endif

                            @if ($featuredCastMembers !== [])
                                <div class="space-y-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                        {{ __('admin.collection.detail.cast_limit_label') }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($featuredCastMembers as $member)
                                            <span class="admin-stat-chip admin-stat-chip-slate">{{ $member }}</span>
                                        @endforeach
                                        @if ($hiddenCastMembersCount > 0)
                                            <span class="admin-stat-chip admin-stat-chip-slate">
                                                {{ trans_choice('admin.collection.detail.cast_more', $hiddenCastMembersCount, ['count' => $hiddenCastMembersCount]) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (filled($item->studio))
                                <p class="text-sm text-zinc-500">
                                    {{ __('admin.collection.detail.studio_secondary', ['studio' => $item->studio]) }}
                                </p>
                            @endif

                            @if (filled($item->network))
                                <p class="text-sm text-zinc-500">
                                    {{ __('admin.collection.detail.network_secondary', ['network' => $item->network]) }}
                                </p>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span class="admin-icon-badge admin-icon-badge-{{ $typeBadgeClass }}">
                            @include('admin.icon', ['name' => 'overview', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.detail.sections.main') }}
                        </h2>
                    </div>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($mainRows as $row)
                            <div class="admin-media-field">
                                <div class="admin-media-field-inner">
                                    <dt class="admin-media-field-label">{{ $row['label'] }}</dt>
                                    <dd class="admin-media-field-value">{{ $row['value'] }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </section>

                @if ($physicalRows !== [])
                    <section class="admin-media-section p-5 sm:p-6">
                        <div class="admin-media-section-header">
                            <span class="admin-icon-badge admin-icon-badge-{{ $typeBadgeClass }}">
                                @include('admin.icon', ['name' => 'sync', 'class' => 'admin-icon'])
                            </span>
                            <h2 class="admin-media-section-label">
                                {{ __('admin.collection.detail.sections.physical') }}
                            </h2>
                        </div>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($physicalRows as $row)
                                <div class="admin-media-field">
                                    <div class="admin-media-field-inner">
                                        <dt class="admin-media-field-label">{{ $row['label'] }}</dt>
                                        <dd class="admin-media-field-value">{{ $row['value'] }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($specificRows !== [])
                    <section class="admin-media-section p-5 sm:p-6">
                        <div class="admin-media-section-header">
                            <span class="admin-icon-badge admin-icon-badge-{{ $typeBadgeClass }}">
                                @include('admin.icon', ['name' => 'modules', 'class' => 'admin-icon'])
                            </span>
                            <h2 class="admin-media-section-label">
                                {{ __('admin.collection.detail.sections.metadata') }}
                            </h2>
                        </div>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($specificRows as $row)
                                <div class="admin-media-field">
                                    <div class="admin-media-field-inner">
                                        <dt class="admin-media-field-label">{{ $row['label'] }}</dt>
                                        <dd class="admin-media-field-value">{{ $row['value'] }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if (filled($item->personal_notes))
                    <section class="admin-media-note">
                        <div class="admin-media-note-inner">
                            <p class="admin-media-note-title">
                                {{ __('admin.collection.detail.sections.notes') }}
                            </p>
                            <p class="admin-media-note-text">{{ $item->personal_notes }}</p>
                        </div>
                    </section>
                @endif
            </div>
        </section>
    </div>
@endsection
