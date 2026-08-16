<?php

namespace App\Http\Requests\Admin;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use Illuminate\Support\Str;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ItemUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $condition = $this->nullableString($this->input('condition'));

        if ($condition !== null && in_array(Str::lower($condition), ['none', 'aucun'], true)) {
            $condition = null;
        }

        $this->merge([
            'condition' => $condition,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ItemType::class)],
            'title' => ['required', 'string', 'max:255'],
            'original_title' => ['nullable', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'between:1800,2100'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'external_tmdb_id' => ['nullable', 'integer', 'min:1'],
            'external_igdb_id' => ['nullable', 'integer', 'min:1'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'physical_format' => ['required', 'string', 'max:64', Rule::in($this->physicalFormatValues())],
            'edition' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:64'],
            'condition' => ['nullable', Rule::enum(ItemCondition::class)],
            'location' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(ItemStatus::class)],
            'is_favorite' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:4000'],
            'personal_notes' => ['nullable', 'string'],
            'acquired_at' => ['nullable', 'date'],
            'runtime_minutes' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'director' => ['nullable', 'string', 'max:255'],
            'studio' => ['nullable', 'string', 'max:255'],
            'age_rating' => ['nullable', 'string', 'max:32'],
            'genres' => ['nullable', 'string', 'max:1000'],
            'cast_members' => ['nullable', 'string', 'max:1000'],
            'platform' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'modes' => ['nullable', 'string', 'max:1000'],
            'min_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'max_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'play_time_minutes' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'designer' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function physicalFormatValues(): array
    {
        $type = ItemType::tryFrom((string) $this->input('type', ''));

        return match ($type) {
            ItemType::Film => ['dvd', 'blu_ray', '4k_uhd', 'vhs', 'digital_copy'],
            ItemType::VideoGame => ['cartridge', 'disc', 'code_in_box', 'collector_edition', 'digital_copy'],
            ItemType::BoardGame => ['box', 'expansion', 'card_game', 'accessory'],
            default => ['dvd', 'blu_ray', '4k_uhd', 'vhs', 'digital_copy', 'cartridge', 'disc', 'code_in_box', 'collector_edition', 'box', 'expansion', 'card_game', 'accessory'],
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minPlayers = $this->numericValue($this->input('min_players'));
            $maxPlayers = $this->numericValue($this->input('max_players'));

            if ($minPlayers !== null && $maxPlayers !== null && $maxPlayers < $minPlayers) {
                $validator->errors()->add('max_players', __('admin.collection.validation.max_players_gte_min_players'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => __('admin.collection.validation.required'),
            'type.required' => __('admin.collection.validation.type_required'),
            'title.required' => __('admin.collection.validation.title_required'),
            'status.required' => __('admin.collection.validation.status_required'),
            'physical_format.required' => __('admin.collection.validation.physical_format_required'),
            'string' => __('admin.collection.validation.string'),
            'integer' => __('admin.collection.validation.integer'),
            'boolean' => __('admin.collection.validation.boolean'),
            'date' => __('admin.collection.validation.date'),
            'between' => __('admin.collection.validation.between'),
            'max.string' => __('admin.collection.validation.max_string'),
            'max.numeric' => __('admin.collection.validation.max_numeric'),
            'max.file' => __('admin.collection.validation.max_file'),
            'min.numeric' => __('admin.collection.validation.min_numeric'),
            'in' => __('admin.collection.validation.in'),
            'image' => __('admin.collection.validation.image'),
            'mimes' => __('admin.collection.validation.mimes'),
            'file' => __('admin.collection.validation.file'),
            'uploaded' => __('admin.collection.validation.uploaded'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('admin.collection.fields.type'),
            'title' => __('admin.collection.fields.title'),
            'original_title' => __('admin.collection.fields.original_title'),
            'release_year' => __('admin.collection.fields.release_year'),
            'barcode' => __('admin.collection.fields.barcode'),
            'external_tmdb_id' => __('admin.collection.fields.external_tmdb_id'),
            'external_igdb_id' => __('admin.collection.fields.external_igdb_id'),
            'cover_path' => __('admin.collection.fields.cover_path'),
            'physical_format' => __('admin.collection.fields.physical_format'),
            'edition' => __('admin.collection.fields.edition'),
            'region' => __('admin.collection.fields.region'),
            'condition' => __('admin.collection.fields.condition'),
            'location' => __('admin.collection.fields.location'),
            'status' => __('admin.collection.fields.status'),
            'is_favorite' => __('admin.collection.fields.is_favorite'),
            'description' => __('admin.collection.fields.description'),
            'personal_notes' => __('admin.collection.fields.personal_notes'),
            'acquired_at' => __('admin.collection.fields.acquired_at'),
            'runtime_minutes' => __('admin.collection.fields.runtime_minutes'),
            'director' => __('admin.collection.fields.director'),
            'studio' => __('admin.collection.fields.studio'),
            'age_rating' => __('admin.collection.fields.age_rating'),
            'genres' => __('admin.collection.fields.genres'),
            'cast_members' => __('admin.collection.fields.cast_members'),
            'platform' => __('admin.collection.fields.platform'),
            'developer' => __('admin.collection.fields.developer'),
            'publisher' => __('admin.collection.fields.publisher'),
            'modes' => __('admin.collection.fields.modes'),
            'min_players' => __('admin.collection.fields.min_players'),
            'max_players' => __('admin.collection.fields.max_players'),
            'play_time_minutes' => __('admin.collection.fields.play_time_minutes'),
            'designer' => __('admin.collection.fields.designer'),
            'cover_image' => __('admin.collection.fields.cover_image'),
            'remove_cover' => __('admin.collection.fields.remove_cover'),
        ];
    }

    public function normalizedData(): array
    {
        $validated = $this->validated();
        $type = $validated['type'];

        return [
            'type' => $type,
            'title' => $validated['title'],
            'original_title' => $validated['original_title'] ?? null,
            'sort_title' => $this->nullableString($validated['sort_title'] ?? null) ?? $this->sortTitleFromTitle($validated['title']),
            'description' => $validated['description'] ?? null,
            'release_year' => $this->integerValue($validated['release_year'] ?? null),
            'barcode' => $this->nullableString($validated['barcode'] ?? null),
            'external_tmdb_id' => $this->integerValue($validated['external_tmdb_id'] ?? null),
            'external_igdb_id' => $this->integerValue($validated['external_igdb_id'] ?? null),
            'cover_path' => $validated['cover_path'] ?? null,
            'physical_format' => $this->nullableString($validated['physical_format'] ?? null),
            'edition' => $this->nullableString($validated['edition'] ?? null),
            'region' => $this->nullableString($validated['region'] ?? null),
            'condition' => $this->nullableString($validated['condition'] ?? null),
            'location' => $this->nullableString($validated['location'] ?? null),
            'status' => $validated['status'],
            'is_favorite' => (bool) ($validated['is_favorite'] ?? false),
            'personal_notes' => $this->nullableString($validated['personal_notes'] ?? null),
            'acquired_at' => $validated['acquired_at'] ?? null,
            'runtime_minutes' => $this->integerValue($validated['runtime_minutes'] ?? null),
            'director' => $this->nullableString($validated['director'] ?? null),
            'cast_members' => $this->csvToArray($validated['cast_members'] ?? null),
            'genres' => $this->csvToArray($validated['genres'] ?? null),
            'studio' => $this->nullableString($validated['studio'] ?? null),
            'age_rating' => $this->nullableString($validated['age_rating'] ?? null),
            'platform' => $this->nullableString($validated['platform'] ?? null),
            'developer' => $this->nullableString($validated['developer'] ?? null),
            'publisher' => $this->nullableString($validated['publisher'] ?? null),
            'modes' => $this->csvToArray($validated['modes'] ?? null),
            'min_players' => $this->integerValue($validated['min_players'] ?? null),
            'max_players' => $this->integerValue($validated['max_players'] ?? null),
            'play_time_minutes' => $this->integerValue($validated['play_time_minutes'] ?? null),
            'designer' => $this->nullableString($validated['designer'] ?? null),
        ] + $this->typeSpecificReset($type);
    }

    /**
     * @return array<string, mixed>
     */
    private function typeSpecificReset(string $type): array
    {
        return match ($type) {
            ItemType::Film->value => [
                'runtime_minutes' => $this->integerValue($this->input('runtime_minutes')),
                'director' => $this->nullableString($this->input('director')),
                'cast_members' => $this->csvToArray($this->input('cast_members')),
                'genres' => $this->csvToArray($this->input('genres')),
                'studio' => $this->nullableString($this->input('studio')),
                'age_rating' => $this->nullableString($this->input('age_rating')),
                'external_tmdb_id' => $this->integerValue($this->input('external_tmdb_id')),
                'platform' => null,
                'developer' => null,
                'publisher' => null,
                'modes' => null,
                'external_igdb_id' => null,
                'min_players' => null,
                'max_players' => null,
                'play_time_minutes' => null,
                'designer' => null,
            ],
            ItemType::VideoGame->value => [
                'runtime_minutes' => null,
                'director' => null,
                'cast_members' => null,
                'genres' => $this->csvToArray($this->input('genres')),
                'studio' => null,
                'age_rating' => $this->nullableString($this->input('age_rating')),
                'external_tmdb_id' => null,
                'platform' => $this->nullableString($this->input('platform')),
                'developer' => $this->nullableString($this->input('developer')),
                'publisher' => $this->nullableString($this->input('publisher')),
                'modes' => $this->csvToArray($this->input('modes')),
                'external_igdb_id' => $this->integerValue($this->input('external_igdb_id')),
                'min_players' => null,
                'max_players' => null,
                'play_time_minutes' => null,
                'designer' => null,
            ],
            ItemType::BoardGame->value => [
                'runtime_minutes' => null,
                'director' => null,
                'cast_members' => null,
                'genres' => $this->csvToArray($this->input('genres')),
                'studio' => null,
                'age_rating' => null,
                'external_tmdb_id' => null,
                'platform' => null,
                'developer' => null,
                'publisher' => $this->nullableString($this->input('publisher')),
                'modes' => null,
                'external_igdb_id' => null,
                'min_players' => $this->integerValue($this->input('min_players')),
                'max_players' => $this->integerValue($this->input('max_players')),
                'play_time_minutes' => $this->integerValue($this->input('play_time_minutes')),
                'designer' => $this->nullableString($this->input('designer')),
            ],
            default => [],
        };
    }

    private function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function numericValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>|null
     */
    private function csvToArray(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $values = array_values(array_filter(array_map(
            static fn (string $entry): string => trim($entry),
            explode(',', $value),
        ), static fn (string $entry): bool => $entry !== ''));

        return $values === [] ? null : $values;
    }

    private function sortTitleFromTitle(string $title): ?string
    {
        $title = trim($title);

        return $title === '' ? null : Str::lower($title);
    }
}
