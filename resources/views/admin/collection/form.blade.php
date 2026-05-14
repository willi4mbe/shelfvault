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
    x-data="{
        type: @js($formType),
        physicalFormat: @js($formPhysicalFormat),
        formatOptions: @js($formatOptions),
        hasValidationErrors: @js($formHasErrors),
        typeMeta: {
            '': { label: @js(__('admin.collection.create.choose_type')), chip: 'slate', rgb: '148 163 184' },
            film: { label: @js(__('admin.collection.types.film')), chip: 'violet', rgb: '99 102 241' },
            video_game: { label: @js(__('admin.collection.types.video_game')), chip: 'emerald', rgb: '20 184 166' },
            board_game: { label: @js(__('admin.collection.types.board_game')), chip: 'amber', rgb: '245 158 11' },
        },
        get currentMeta() {
            return this.typeMeta[this.type] ?? this.typeMeta[''];
        },
        get currentLabel() {
            return this.currentMeta.label;
        },
        get currentChip() {
            return this.currentMeta.chip;
        },
        get currentAccent() {
            return this.currentMeta.rgb;
        },
        syncPhysicalFormat() {
            const options = this.formatOptions[this.type] ?? {};

            if (!Object.prototype.hasOwnProperty.call(options, this.physicalFormat)) {
                this.physicalFormat = '';
            }
        },
    }"
    x-init="if (!hasValidationErrors) syncPhysicalFormat()"
    x-bind:style="'--media-accent: ' + currentAccent + ';'"
>
    <div class="admin-media-hero px-5 py-5 sm:px-6 sm:py-6 lg:p-8">
        <div class="admin-media-hero-inner flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span x-bind:class="'admin-stat-chip admin-stat-chip-' + currentChip">
                        <span x-text="currentLabel"></span>
                    </span>
                    @if ($formPhysicalFormat !== '')
                        <span class="admin-stat-chip admin-stat-chip-sky">
                            {{ $formPhysicalFormat }}
                        </span>
                    @endif
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

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1.95fr)]">
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
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $item->title ?: __('admin.collection.create.title') }}" class="admin-media-cover-image">
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

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label class="block space-y-2" data-initial-type="{{ $formType }}" data-initial-physical-format="{{ $formPhysicalFormat }}">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.type') }}</span>
                            <select name="type" x-model="type" x-on:change="syncPhysicalFormat()" class="{{ $commonInputClass }}">
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

                        <label class="block space-y-2 md:col-span-2 xl:col-span-3">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.title') }}</span>
                            <input type="text" name="title" value="{{ old('title', $item->title) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.original_title') }}</span>
                            <input type="text" name="original_title" value="{{ old('original_title', $item->original_title) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.release_year') }}</span>
                            <input type="number" name="release_year" value="{{ old('release_year', $item->release_year) }}" min="1800" max="2100" inputmode="numeric" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.barcode') }}</span>
                            <input type="text" name="barcode" value="{{ old('barcode', $item->barcode) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-700">
                            <input type="hidden" name="is_favorite" value="0">
                            <input type="checkbox" name="is_favorite" value="1" @checked((bool) old('is_favorite', $item->is_favorite)) class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500">
                            <span>{{ __('admin.collection.fields.is_favorite') }}</span>
                        </label>
                    </div>
                </section>

                <section class="admin-media-section p-5 sm:p-6">
                    <div class="admin-media-section-header">
                        <span x-bind:class="'admin-icon-badge admin-icon-badge-' + currentChip">
                            @include('admin.icon', ['name' => 'sync', 'class' => 'admin-icon'])
                        </span>
                        <h2 class="admin-media-section-label">
                            {{ __('admin.collection.detail.sections.physical') }}
                        </h2>
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
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.location') }}</span>
                            <input type="text" name="location" value="{{ old('location', $item->location) }}" class="{{ $commonInputClass }}">
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.acquired_at') }}</span>
                            <input type="date" name="acquired_at" value="{{ old('acquired_at', optional($item->acquired_at)->toDateString()) }}" class="{{ $commonInputClass }}">
                        </label>
                    </div>
                </section>
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
                        <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.personal_notes') }}</span>
                        <textarea name="personal_notes" rows="4" class="{{ $commonInputClass }}">{{ old('personal_notes', $item->personal_notes) }}</textarea>
                    </label>
                </div>
            </div>
        </section>
    </form>
</section>
