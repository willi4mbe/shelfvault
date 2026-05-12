@php
    use App\Enums\ItemType;
    use App\Enums\ItemStatus;
    use App\Models\Item;

    /** @var Item $item */
    $formType = old('type', $selectedType ?? ($item->type?->value ?? ItemType::Film->value));
    $formPhysicalFormat = old('physical_format', $item->physical_format ?? '');
    $formHasErrors = $errors->any();
    $itemToText = static function (?array $values): string {
        if (! is_array($values) || $values === []) {
            return '';
        }

        return implode(', ', $values);
    };

    $typeValue = fn (string $key) => old($key, $item->{$key} ?? null);
    $commonInputClass = 'w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-950 outline-none transition focus:border-zinc-400 focus:bg-white focus:ring-4 focus:ring-zinc-100';
@endphp

<section class="space-y-6"
    x-data="{
        type: @js($formType),
        physicalFormat: @js($formPhysicalFormat),
        formatOptions: @js($formatOptions),
        hasValidationErrors: @js($formHasErrors),
        syncPhysicalFormat() {
            const options = this.formatOptions[this.type] ?? {};

            if (!Object.prototype.hasOwnProperty.call(options, this.physicalFormat)) {
                this.physicalFormat = '';
            }
        },
    }"
    x-init="if (!hasValidationErrors) syncPhysicalFormat()"
>
    <div class="admin-topbar flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                {{ $mode === 'create' ? __('admin.collection.create.kicker') : __('admin.collection.edit.kicker') }}
            </p>
            <h1 class="mt-2 text-[1.95rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.4rem]">
                {{ $mode === 'create' ? __('admin.collection.create.title') : __('admin.collection.edit.title') }}
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-600">
                {{ $mode === 'create' ? __('admin.collection.create.subtitle') : __('admin.collection.edit.subtitle') }}
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ $backUrl }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                {{ __('admin.collection.actions.back') }}
            </a>
            <a href="{{ route('admin') }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                {{ __('admin.collection.back_to_dashboard') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-[22px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">{{ __('admin.collection.validation.heading') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <section class="admin-panel space-y-5">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="block space-y-2" data-initial-type="{{ $formType }}" data-initial-physical-format="{{ $formPhysicalFormat }}">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.type') }}</span>
                    <select name="type" x-model="type" x-on:change="syncPhysicalFormat()" class="{{ $commonInputClass }}">
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

                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.physical_format') }}</span>
                    <select name="physical_format" x-model="physicalFormat" class="{{ $commonInputClass }}">
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

                <label class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-700">
                    <input type="hidden" name="is_favorite" value="0">
                    <input type="checkbox" name="is_favorite" value="1" @checked((bool) old('is_favorite', $item->is_favorite)) class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500">
                    <span>{{ __('admin.collection.fields.is_favorite') }}</span>
                </label>

                <label class="block space-y-2 md:col-span-2 xl:col-span-3">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.personal_notes') }}</span>
                    <textarea name="personal_notes" rows="4" class="{{ $commonInputClass }}">{{ old('personal_notes', $item->personal_notes) }}</textarea>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-zinc-700">{{ __('admin.collection.fields.acquired_at') }}</span>
                    <input type="date" name="acquired_at" value="{{ old('acquired_at', optional($item->acquired_at)->toDateString()) }}" class="{{ $commonInputClass }}">
                </label>
            </div>
        </section>

        <section class="admin-panel space-y-5">
            <div x-show="type === '{{ ItemType::Film->value }}'" x-cloak class="space-y-4">
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

            <div x-show="type === '{{ ItemType::VideoGame->value }}'" x-cloak class="space-y-4">
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

            <div x-show="type === '{{ ItemType::BoardGame->value }}'" x-cloak class="space-y-4">
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

        <section class="flex flex-col gap-3 sm:flex-row">
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800">
                {{ $mode === 'create' ? __('admin.collection.actions.create') : __('admin.collection.actions.save') }}
            </button>
            <a href="{{ $backUrl }}" class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950">
                {{ __('admin.collection.actions.cancel') }}
            </a>
        </section>
    </form>
</section>
