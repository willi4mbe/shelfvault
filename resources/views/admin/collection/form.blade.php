@php
    use App\Enums\ItemStatus;
    use App\Enums\ItemType;
    use App\Models\Item;

    /** @var Item $item */
    $formType = old('type', $selectedType ?? ($item->type?->value ?? ''));
    $formPhysicalFormat = old('physical_format', $item->physical_format ?? '');
    $formHasErrors = $errors->any();
    $itemToText = static function (?array $values): string {
        if (! is_array($values) || $values === []) {
            return '';
        }

        return implode(', ', $values);
    };

    $commonInputClass = 'w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100';
    $coverUrl = $item->coverUrl();
    $typeConfig = match ($formType) {
        'film' => ['rgb' => '99 102 241', 'chip' => 'violet'],
        'video_game' => ['rgb' => '20 184 166', 'chip' => 'emerald'],
        'board_game' => ['rgb' => '245 158 11', 'chip' => 'amber'],
        default => ['rgb' => '148 163 184', 'chip' => 'neutral'],
    };
@endphp

<section class="space-y-6"
    x-data="metadataLookup({
        type: @js($formType),
        title: @js(old('title', $item->title)),
        originalTitle: @js(old('original_title', $item->original_title)),
        releaseYear: @js(old('release_year', $item->release_year)),
        barcode: @js(old('barcode', $item->barcode)),
        physicalFormat: @js($formPhysicalFormat),
        coverPath: @js(old('cover_path', $item->cover_path)),
        coverPreviewUrl: @js($coverUrl),
        hasValidationErrors: @js($formHasErrors),
        formatOptions: @js($formatOptions),
        titleSearchUrl: @js(route('admin.collection.metadata.search')),
        importUrl: @js(route('admin.collection.metadata.import')),
        labels: {
            choose: @js(__('admin.collection.lookup.choose')),
            searching: @js(__('admin.collection.lookup.searching')),
            resultsFound: @js(__('admin.collection.metadata.results_found')),
            noResultFound: @js(__('admin.collection.lookup.no_result_found')),
            tmdbNotConfigured: @js(__('admin.collection.metadata.tmdb_not_configured')),
            chooseTypeBeforeSearching: @js(__('admin.collection.lookup.choose_type_before_searching')),
            enterTitleToSearch: @js(__('admin.collection.lookup.enter_title_to_search')),
            automaticSearchNotAvailableForThisType: @js(__('admin.collection.lookup.automatic_search_not_available_for_this_type')),
            searchError: @js(__('admin.collection.metadata.search_error')),
            metadataImported: @js(__('admin.collection.metadata.metadata_imported')),
            coverImported: @js(__('admin.collection.metadata.cover_imported')),
            coverNotImported: @js(__('admin.collection.metadata.cover_not_imported')),
            posterImportFailed: @js(__('admin.collection.metadata.poster_import_failed')),
        },
        typeLabels: {
            none: @js(__('admin.collection.create.choose_type')),
            film: @js(__('admin.collection.types.film')),
            video_game: @js(__('admin.collection.types.video_game')),
            board_game: @js(__('admin.collection.types.board_game')),
        },
    })"
    x-bind:style="'--media-accent: ' + currentAccent + ';'"
    @keydown.escape.window="resultsOpen && closeResults()"
    @barcode-cover-preview.window="coverPath = $event.detail.path ?? coverPath; coverPreviewUrl = $event.detail.url ?? coverPreviewUrl;"
>
    <div class="admin-media-hero px-5 py-5 sm:px-6 sm:py-6 lg:p-8">
        <div class="admin-media-hero-inner flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span x-bind:class="'admin-stat-chip admin-stat-chip-' + currentChip">
                        <span x-text="currentLabel"></span>
                    </span>
                    <template x-if="physicalFormat">
                        <span class="admin-stat-chip admin-stat-chip-sky" x-text="physicalFormat"></span>
                    </template>
                </div>

                <div class="max-w-3xl space-y-3">
                    <p class="text-sm font-semibold tracking-[0.16em] text-zinc-500 uppercase" x-text="currentLabel"></p>
                    <h1 class="text-[clamp(2.2rem,4vw,3.5rem)] font-semibold leading-[0.95] tracking-[-0.05em] text-zinc-950">
                        {{ $mode === 'create' ? __('admin.collection.create.title') : __('admin.collection.edit.title') }}
                    </h1>
                </div>
            </div>

            <div class="admin-media-actions xl:justify-end">
                <a href="{{ $backUrl }}" class="admin-media-button admin-media-button-secondary">
                    {{ __('admin.collection.actions.cancel') }}
                </a>
                <a href="{{ route('admin') }}" class="admin-media-button admin-media-button-secondary">
                    {{ __('admin.collection.back_to_dashboard') }}
                </a>
            </div>
        </div>
    </div>

    <section class="sticky top-4 z-30">
        <div class="admin-media-hero px-4 py-4 sm:px-5">
            <div class="admin-media-actions justify-end">
                <button type="submit" form="collection-form" class="admin-media-button admin-media-button-primary">
                    {{ $mode === 'create' ? __('admin.collection.add_item') : __('admin.collection.actions.save') }}
                </button>
                <a href="{{ $backUrl }}" class="admin-media-button admin-media-button-secondary">
                    {{ __('admin.collection.actions.cancel') }}
                </a>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-[22px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
            <p class="font-semibold">{{ __('admin.collection.validation.heading') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="collection-form" method="POST" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <input type="hidden" name="external_tmdb_id" value="{{ old('external_tmdb_id', $item->external_tmdb_id) }}">
        <input type="hidden" name="cover_path" x-model="coverPath">

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1.95fr)]">
            <aside class="space-y-6">
                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'collection', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.fields.cover_image') }}
                        </h2>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="admin-media-cover-shell p-5 sm:p-6">
                            <div class="admin-media-cover-frame">
                                <template x-if="coverPreviewUrl">
                                    <img :src="coverPreviewUrl" alt="{{ $item->title ?: __('admin.collection.create.title') }}" class="admin-media-cover-image">
                                </template>
                                <template x-if="!coverPreviewUrl">
                                    <div class="admin-media-cover-placeholder">
                                        <span class="admin-media-placeholder-mark">
                                            @include('admin.icon', ['name' => 'collection', 'class' => 'h-6 w-6'])
                                        </span>
                                        <div class="space-y-2">
                                            <p class="admin-media-cover-placeholder-title">{{ __('admin.collection.placeholders.no_cover') }}</p>
                                            <p class="admin-media-cover-placeholder-text">{{ __('admin.collection.detail.cover_hint') }}</p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <label class="block space-y-2">
                            <span class="sr-only">{{ __('admin.collection.fields.cover_image') }}</span>
                            <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="{{ $commonInputClass }} py-2.5">
                        </label>

                        <p class="text-sm leading-6 text-zinc-600">
                            {{ __('admin.collection.help.cover_image') }}
                        </p>

                        @if ($mode === 'edit')
                            <label class="inline-flex items-center gap-3 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm">
                                <input type="checkbox" name="remove_cover" value="1" @checked(old('remove_cover', false)) class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500">
                                <span>{{ __('admin.collection.fields.remove_cover') }}</span>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'search', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.metadata.tmdb_search') }}
                        </h2>
                    </div>

                    <div class="mt-5 space-y-4">
                        <p class="text-sm leading-6 text-zinc-600">
                            {{ __('admin.collection.metadata.physical_fields_manual') }}
                        </p>

                        <button
                            type="button"
                            class="admin-media-button admin-media-button-primary w-full justify-center"
                            @click="searchTitle()"
                            :disabled="titleBusy"
                        >
                            {{ __('admin.collection.metadata.search_on_tmdb') }}
                        </button>

                        <div class="space-y-1 text-xs leading-5 text-zinc-500">
                            <p x-cloak x-show="titleBusy" x-text="titleMessage" aria-live="polite"></p>
                            <p x-cloak x-show="!titleBusy && titleMessage" x-text="titleMessage" aria-live="polite"></p>
                            <p x-cloak x-show="!titleBusy && importNotice" x-text="importNotice" aria-live="polite"></p>
                        </div>

                        <div
                            x-cloak
                            x-show="resultsOpen && resultsScope === 'title'"
                            x-transition.opacity
                            class="admin-media-lookup-inline space-y-4 border border-zinc-200 bg-white/90 p-4 shadow-sm sm:p-5"
                        >
                            <div class="space-y-1">
                                <h3 class="text-lg font-semibold text-zinc-950">
                                    {{ __('admin.collection.metadata.results_found') }}
                                </h3>
                                <p class="text-sm leading-6 text-zinc-600" x-text="resultsMessage"></p>
                            </div>

                            <div class="space-y-3">
                                <template x-for="candidate in resultsCandidates" :key="candidate.tmdb_id ?? candidate.id">
                                    <article class="rounded-[22px] border border-zinc-200 bg-white p-3 shadow-sm">
                                        <div class="flex gap-3">
                                            <template x-if="candidate.poster_url">
                                                <img :src="candidate.poster_url" :alt="candidate.title" class="h-28 w-20 rounded-2xl object-cover shadow-sm">
                                            </template>
                                            <template x-if="!candidate.poster_url">
                                                <div class="flex h-28 w-20 flex-none items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-100 text-zinc-400">
                                                    @include('admin.icon', ['name' => 'collection', 'class' => 'h-5 w-5'])
                                                </div>
                                            </template>

                                            <div class="min-w-0 flex-1 space-y-2">
                                                <div class="space-y-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h4 class="min-w-0 flex-1 text-sm font-semibold text-zinc-950" x-text="candidate.title"></h4>
                                                        <span x-cloak x-show="candidate.release_year" class="rounded-full bg-zinc-100 px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600" x-text="candidate.release_year"></span>
                                                    </div>

                                                    <p x-cloak x-show="candidate.original_title && candidate.original_title !== candidate.title" class="truncate text-xs text-zinc-500" x-text="candidate.original_title"></p>
                                                </div>

                                                <p x-cloak x-show="candidate.overview" class="line-clamp-3 text-xs leading-5 text-zinc-600" x-text="candidate.overview"></p>

                                                <div class="flex justify-end">
                                                    <button
                                                        type="button"
                                                        class="admin-media-button admin-media-button-primary text-sm"
                                                        @click="chooseCandidate(candidate)"
                                                        :disabled="importBusy && activeCandidateId !== (candidate.tmdb_id ?? candidate.id)"
                                                    >
                                                        {{ __('admin.collection.lookup.choose') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>

            <div class="space-y-6">
                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'overview', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.detail.sections.main') }}
                        </h2>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block space-y-2" data-initial-type="{{ $formType }}" data-initial-physical-format="{{ $formPhysicalFormat }}">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.type') }}</span>
                            <select name="type" x-model="type" x-on:change="syncPhysicalFormat(); closeResults(); clearTitleState(); clearBarcodeState()" class="{{ $commonInputClass }}">
                                <option value="">{{ __('admin.collection.create.choose_type') }}</option>
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($formType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.status') }}</span>
                            <select name="status" class="{{ $commonInputClass }}">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $item->status?->value ?? ItemStatus::Owned->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.condition') }}</span>
                            <select name="condition" class="{{ $commonInputClass }}">
                                @foreach ($conditionOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('condition', $item->condition?->value ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-700">
                            <input type="hidden" name="is_favorite" value="0">
                            <input type="checkbox" name="is_favorite" value="1" @checked((bool) old('is_favorite', $item->is_favorite)) class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500">
                            <span>{{ __('admin.collection.fields.is_favorite') }}</span>
                        </label>

                        <label class="block space-y-2 md:col-span-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.title') }}</span>
                            <input type="text" name="title" x-model="title" @input="closeResults(); clearTitleState()" @keydown.enter.prevent="searchTitle()" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.original_title') }}</span>
                            <input type="text" name="original_title" x-model="originalTitle" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.release_year') }}</span>
                            <input type="number" name="release_year" x-model="releaseYear" min="1800" max="2100" inputmode="numeric" class="{{ $commonInputClass }}">
                        </label>
                    </div>
                </section>

                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'sync', 'class' => 'admin-icon'])
                        </span>
                        <div>
                            <h2 class="admin-media-section-label">
                                {{ __('admin.collection.detail.sections.physical') }}
                            </h2>
                            <p class="mt-1 text-xs font-medium text-zinc-500">
                                {{ __('admin.collection.metadata.physical_fields_manual') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label class="block space-y-2" data-initial-physical-format="{{ $formPhysicalFormat }}">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.physical_format') }}</span>
                            <input type="hidden" name="physical_format" :value="physicalFormat">
                            <select x-model="physicalFormat" class="{{ $commonInputClass }}">
                                @if ($formHasErrors && $formPhysicalFormat !== '' && ! array_key_exists($formPhysicalFormat, $formatOptions[$formType] ?? []))
                                    <option value="{{ $formPhysicalFormat }}" selected>{{ $formPhysicalFormat }}</option>
                                @endif
                                <option value="">{{ __('admin.collection.placeholders.none') }}</option>
                                <template x-for="entry in Object.entries(formatOptions[type] ?? {}).filter(([value]) => value !== '')" :key="entry[0]">
                                    <option :value="entry[0]" x-text="entry[1]"></option>
                                </template>
                            </select>
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.edition') }}</span>
                            <input type="text" name="edition" value="{{ old('edition', $item->edition) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.region') }}</span>
                            <input type="text" name="region" value="{{ old('region', $item->region) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.barcode') }}</span>
                            <input type="text" name="barcode" x-model="barcode" @input="clearBarcodeState()" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.location') }}</span>
                            <input type="text" name="location" value="{{ old('location', $item->location) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.acquired_at') }}</span>
                            <input type="date" name="acquired_at" value="{{ old('acquired_at', optional($item->acquired_at)->toDateString()) }}" class="{{ $commonInputClass }}">
                        </label>
                    </div>
                </section>

                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'modules', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.detail.sections.metadata') }}
                        </h2>
                    </div>

                    <div x-show="type === '{{ ItemType::Film->value }}'" x-cloak class="mt-5 space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.runtime_minutes') }}</span>
                                <input type="number" name="runtime_minutes" value="{{ old('runtime_minutes', $item->runtime_minutes) }}" min="1" inputmode="numeric" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.director') }}</span>
                                <input type="text" name="director" value="{{ old('director', $item->director) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.studio') }}</span>
                                <input type="text" name="studio" value="{{ old('studio', $item->studio) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.age_rating') }}</span>
                                <input type="text" name="age_rating" value="{{ old('age_rating', $item->age_rating) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.genres') }}</span>
                                <textarea name="genres" rows="3" placeholder="{{ __('admin.collection.fields.csv_placeholder') }}" class="{{ $commonInputClass }}">{{ old('genres', $itemToText($item->genres)) }}</textarea>
                            </label>
                            <label class="block space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.cast_members') }}</span>
                                <textarea name="cast_members" rows="3" placeholder="{{ __('admin.collection.fields.csv_placeholder') }}" class="{{ $commonInputClass }}">{{ old('cast_members', $itemToText($item->cast_members)) }}</textarea>
                            </label>
                        </div>
                    </div>

                    <div x-show="type === '{{ ItemType::VideoGame->value }}'" x-cloak class="mt-5 space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.platform') }}</span>
                                <input type="text" name="platform" value="{{ old('platform', $item->platform) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.developer') }}</span>
                                <input type="text" name="developer" value="{{ old('developer', $item->developer) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.publisher') }}</span>
                                <input type="text" name="publisher" value="{{ old('publisher', $item->publisher) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.age_rating') }}</span>
                                <input type="text" name="age_rating" value="{{ old('age_rating', $item->age_rating) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.genres') }}</span>
                                <textarea name="genres" rows="3" placeholder="{{ __('admin.collection.fields.csv_placeholder') }}" class="{{ $commonInputClass }}">{{ old('genres', $itemToText($item->genres)) }}</textarea>
                            </label>
                            <label class="block space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.modes') }}</span>
                                <textarea name="modes" rows="3" placeholder="{{ __('admin.collection.fields.csv_placeholder') }}" class="{{ $commonInputClass }}">{{ old('modes', $itemToText($item->modes)) }}</textarea>
                            </label>
                        </div>
                    </div>

                    <div x-show="type === '{{ ItemType::BoardGame->value }}'" x-cloak class="mt-5 space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.min_players') }}</span>
                                <input type="number" name="min_players" value="{{ old('min_players', $item->min_players) }}" min="1" inputmode="numeric" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.max_players') }}</span>
                                <input type="number" name="max_players" value="{{ old('max_players', $item->max_players) }}" min="1" inputmode="numeric" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.play_time_minutes') }}</span>
                                <input type="number" name="play_time_minutes" value="{{ old('play_time_minutes', $item->play_time_minutes) }}" min="1" inputmode="numeric" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.designer') }}</span>
                                <input type="text" name="designer" value="{{ old('designer', $item->designer) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.publisher') }}</span>
                                <input type="text" name="publisher" value="{{ old('publisher', $item->publisher) }}" class="{{ $commonInputClass }}">
                            </label>
                            <label class="block space-y-2 md:col-span-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.genres') }}</span>
                                <textarea name="genres" rows="3" placeholder="{{ __('admin.collection.fields.csv_placeholder') }}" class="{{ $commonInputClass }}">{{ old('genres', $itemToText($item->genres)) }}</textarea>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="admin-media-note">
                    <div class="admin-media-note-inner">
                        <p class="admin-media-note-title">
                            {{ __('admin.collection.detail.sections.notes') }}
                        </p>

                        <div class="mt-4">
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.description') }}</span>
                                <textarea name="description" rows="3" class="{{ $commonInputClass }}">{{ old('description', $item->description) }}</textarea>
                            </label>
                        </div>

                        <div class="mt-4">
                            <label class="block space-y-2">
                                <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.personal_notes') }}</span>
                                <textarea name="personal_notes" rows="4" class="{{ $commonInputClass }}">{{ old('personal_notes', $item->personal_notes) }}</textarea>
                            </label>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </form>
</section>
