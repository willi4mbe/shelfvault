@extends('library.layout')

@section('title', $item->title.' - '.$libraryName)

@section('content')
    @php
        $coverUrl = $item->coverUrl();
        $typeLabel = __('library.navigation.'.$item->type->value);
        $formatKey = $item->physical_format === '4k_uhd' ? 'four_k_uhd' : $item->physical_format;
        $formatTranslationKey = $formatKey ? 'library.formats.'.$item->type->value.'.'.$formatKey : null;
        $formatLabel = $formatTranslationKey && trans()->has($formatTranslationKey)
            ? __($formatTranslationKey)
            : $item->physical_format;
        $conditionLabel = $item->condition?->value ? __('library.conditions.'.$item->condition->value) : null;
        $locationValue = ($locationsEnabled ?? true) ? $item->location : null;
        $favoriteLabel = $item->is_favorite ? __('library.values.yes') : __('library.values.no');
        $castMembers = is_array($item->cast_members)
            ? array_values(array_filter($item->cast_members, fn ($member) => is_string($member) && trim($member) !== ''))
            : [];
        $genres = is_array($item->genres) ? implode(', ', $item->genres) : null;
        $modes = is_array($item->modes) ? implode(', ', $item->modes) : null;
        $mainFacts = array_filter([
            __('library.detail.type') => __('library.types.'.$item->type->value),
            __('library.detail.title') => $item->title,
            __('library.detail.original_title') => $item->original_title,
            __('library.detail.release_year') => $item->release_year,
            __('library.detail.end_year') => $item->end_year,
            __('library.detail.barcode') => $item->barcode,
            __('library.detail.condition') => $conditionLabel,
            __('library.detail.favorite') => $favoriteLabel,
        ], fn ($value) => filled($value));
        $physicalFacts = array_filter([
            __('library.detail.physical_format') => $formatLabel,
            __('library.detail.edition') => $item->edition,
            __('library.detail.region') => $item->region,
            __('library.detail.location') => $locationValue,
            __('library.detail.acquired_at') => $item->acquired_at?->format('Y-m-d'),
        ], fn ($value) => filled($value));
        $metadataFacts = array_filter([
            __('library.detail.runtime_label') => $item->runtime_minutes ? __('library.detail.runtime', ['minutes' => $item->runtime_minutes]) : null,
            __('library.detail.seasons_label') => $item->season_count ? trans_choice('library.detail.seasons', $item->season_count, ['count' => $item->season_count]) : null,
            __('library.detail.episodes_label') => $item->episode_count ? trans_choice('library.detail.episodes', $item->episode_count, ['count' => $item->episode_count]) : null,
            __('library.detail.players_label') => $item->min_players && $item->max_players ? __('library.detail.players', ['min' => $item->min_players, 'max' => $item->max_players]) : null,
            __('library.detail.play_time_label') => $item->play_time_minutes ? __('library.detail.play_time', ['minutes' => $item->play_time_minutes]) : null,
            __('library.detail.director') => $item->director,
            __('library.detail.showrunner') => $item->showrunner,
            __('library.detail.studio') => $item->studio,
            __('library.detail.network') => $item->network,
            __('library.detail.platform') => $item->platform,
            __('library.detail.developer') => $item->developer,
            __('library.detail.publisher') => $item->publisher,
            __('library.detail.designer') => $item->designer,
            __('library.detail.age_rating') => $item->age_rating,
            __('library.detail.genres') => $genres,
            __('library.detail.modes') => $modes,
            __('library.detail.tmdb_id') => $item->external_tmdb_id,
            __('library.detail.igdb_id') => $item->external_igdb_id,
        ], fn ($value) => filled($value));
        $physicalHighlights = array_filter([
            __('library.detail.format') => $formatLabel,
            __('library.detail.condition') => $conditionLabel,
            __('library.detail.location') => $locationValue,
        ], fn ($value) => filled($value));
    @endphp

    <div class="space-y-10">
        <section class="library-detail-hero">
            <div class="library-detail-cover">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover">
                @else
                    <div class="library-poster-placeholder">
                        @include('admin.icon', ['name' => 'collection', 'class' => 'h-12 w-12'])
                        <span class="sr-only">{{ __('library.empty.no_cover') }}</span>
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <a href="{{ route('library.type', $item->type->value) }}" class="library-back-icon" aria-label="{{ __('library.actions.back_type', ['type' => $typeLabel]) }}">
                    <span aria-hidden="true">‹</span>
                </a>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="library-type-badge-static">{{ __('library.types.'.$item->type->value) }}</span>
                    @if ($item->is_favorite)
                        <span class="library-type-badge-static">{{ __('library.badges.favorite') }}</span>
                    @endif
                </div>
                <h1 class="mt-4 text-4xl font-semibold text-white sm:text-6xl">{{ $item->title }}</h1>
                @if ($item->original_title && $item->original_title !== $item->title)
                    <p class="mt-2 text-lg text-white/48">{{ $item->original_title }}</p>
                @endif

                <div class="library-detail-facts">
                    @if ($item->release_year)
                        <span>{{ $item->release_year }}</span>
                    @endif
                    @if ($item->runtime_minutes)
                        <span>{{ __('library.detail.runtime', ['minutes' => $item->runtime_minutes]) }}</span>
                    @endif
                    @if ($item->season_count)
                        <span>{{ trans_choice('library.detail.seasons', $item->season_count, ['count' => $item->season_count]) }}</span>
                    @endif
                    @if ($item->episode_count)
                        <span>{{ trans_choice('library.detail.episodes', $item->episode_count, ['count' => $item->episode_count]) }}</span>
                    @endif
                    @if ($item->min_players && $item->max_players)
                        <span>{{ __('library.detail.players', ['min' => $item->min_players, 'max' => $item->max_players]) }}</span>
                    @endif
                    @if ($item->play_time_minutes)
                        <span>{{ __('library.detail.play_time', ['minutes' => $item->play_time_minutes]) }}</span>
                    @endif
                </div>

                @if ($item->description)
                    <div class="mt-8 max-w-3xl">
                        <h2 class="library-kicker">{{ __('library.detail.overview') }}</h2>
                        <p class="mt-3 text-base leading-8 text-white/68">{{ $item->description }}</p>
                    </div>
                @endif

                @if ($activeLoan)
                    <div class="library-loan-note mt-8">
                        <p class="text-sm font-semibold text-white">{{ __('library.detail.loaned_to', ['name' => $activeLoan->borrower_name]) }}</p>
                        <p class="mt-1 text-xs text-white/52">
                            {{ __('library.detail.loaned_since', ['date' => $activeLoan->loaned_at?->format('d/m/Y')]) }}
                            @if ($activeLoan->expected_return_at)
                                / {{ __('library.detail.expected_return', ['date' => $activeLoan->expected_return_at->format('d/m/Y')]) }}
                            @endif
                        </p>
                    </div>
                @endif

                @if ($physicalHighlights)
                    <dl class="library-detail-highlight-grid mt-5">
                        @foreach ($physicalHighlights as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </section>

        @foreach ([
            __('library.detail.main_information') => $mainFacts,
            __('library.detail.physical_details') => $physicalFacts,
        ] as $sectionTitle => $facts)
            @if ($facts)
                <section class="library-section">
                    <div class="library-section-heading">
                        <div>
                            <h2>{{ $sectionTitle }}</h2>
                        </div>
                    </div>
                    <dl class="library-metadata-grid">
                        @foreach ($facts as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        @endforeach

        @if ($castMembers)
            <section class="library-section">
                <div class="library-section-heading">
                    <div>
                        <h2>{{ __('library.detail.cast') }}</h2>
                    </div>
                </div>
                <div class="library-detail-chip-list">
                    @foreach ($castMembers as $member)
                        <span>{{ $member }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($metadataFacts)
            <section class="library-section">
                <div class="library-section-heading">
                    <div>
                        <h2>{{ __('library.detail.metadata') }}</h2>
                    </div>
                </div>
                <dl class="library-metadata-grid">
                    @foreach ($metadataFacts as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($item->personal_notes)
            <section class="library-section">
                <div class="library-section-heading">
                    <div>
                        <h2>{{ __('library.detail.notes') }}</h2>
                    </div>
                </div>
                <div class="library-detail-note">{{ $item->personal_notes }}</div>
            </section>
        @endif

    </div>
@endsection
