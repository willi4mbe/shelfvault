<?php

namespace App\Services\Metadata\Providers;

use App\Models\Item;
use App\Services\Metadata\BarcodeLookupResult;
use App\Services\Metadata\Contracts\BarcodeLookupProvider;

class LocalItemBarcodeLookupProvider implements BarcodeLookupProvider
{
    public function lookup(string $barcode, ?string $typeHint = null): BarcodeLookupResult
    {
        $item = Item::query()
            ->where('barcode', trim($barcode))
            ->first();

        if (! $item) {
            return BarcodeLookupResult::notFound($barcode, class_basename(self::class));
        }

        $data = [
            'type' => $item->type?->value,
            'title' => $item->title,
            'original_title' => $item->original_title,
            'release_year' => $item->release_year,
            'barcode' => $item->barcode,
            'cover_path' => $item->cover_path,
            'cover_url' => $item->coverUrl(),
            'physical_format' => $item->physical_format,
            'edition' => $item->edition,
            'region' => $item->region,
            'condition' => $item->condition?->value,
            'location' => $item->location,
            'status' => $item->status?->value,
            'is_favorite' => $item->is_favorite,
            'description' => $item->description,
            'personal_notes' => $item->personal_notes,
            'acquired_at' => $item->acquired_at?->toDateString(),
            'runtime_minutes' => $item->runtime_minutes,
            'director' => $item->director,
            'cast_members' => $item->cast_members,
            'genres' => $item->genres,
            'studio' => $item->studio,
            'age_rating' => $item->age_rating,
            'platform' => $item->platform,
            'developer' => $item->developer,
            'publisher' => $item->publisher,
            'modes' => $item->modes,
            'min_players' => $item->min_players,
            'max_players' => $item->max_players,
            'play_time_minutes' => $item->play_time_minutes,
            'designer' => $item->designer,
        ];

        return BarcodeLookupResult::found(array_filter(
            $data,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        ), class_basename(self::class));
    }
}
