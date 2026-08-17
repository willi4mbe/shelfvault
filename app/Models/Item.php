<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @use HasFactory<ItemFactory>
 */
class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'original_title',
        'sort_title',
        'description',
        'release_year',
        'end_year',
        'barcode',
        'cover_path',
        'physical_format',
        'edition',
        'region',
        'condition',
        'location',
        'status',
        'is_favorite',
        'personal_notes',
        'acquired_at',
        'runtime_minutes',
        'description_original',
        'director',
        'cast_members',
        'genres',
        'studio',
        'age_rating',
        'external_tmdb_id',
        'season_count',
        'episode_count',
        'showrunner',
        'network',
        'platform',
        'developer',
        'publisher',
        'modes',
        'external_igdb_id',
        'min_players',
        'max_players',
        'play_time_minutes',
        'designer',
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'status' => ItemStatus::class,
            'condition' => ItemCondition::class,
            'is_favorite' => 'bool',
            'release_year' => 'integer',
            'end_year' => 'integer',
            'runtime_minutes' => 'integer',
            'season_count' => 'integer',
            'episode_count' => 'integer',
            'cast_members' => 'array',
            'genres' => 'array',
            'modes' => 'array',
            'min_players' => 'integer',
            'max_players' => 'integer',
            'play_time_minutes' => 'integer',
            'acquired_at' => 'date',
        ];
    }

    public function itemLoans(): HasMany
    {
        return $this->hasMany(ItemLoan::class)->latest('loaned_at');
    }

    public function coverUrl(): ?string
    {
        if (! filled($this->cover_path)) {
            return null;
        }

        if (filter_var($this->cover_path, FILTER_VALIDATE_URL)) {
            return $this->cover_path;
        }

        if (! Storage::disk('public')->exists($this->cover_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_path);
    }

    public function scopeFilm(Builder $query): Builder
    {
        return $query->where('type', ItemType::Film->value);
    }

    public function scopeVideoGame(Builder $query): Builder
    {
        return $query->where('type', ItemType::VideoGame->value);
    }

    public function scopeTvSeries(Builder $query): Builder
    {
        return $query->where('type', ItemType::TvSeries->value);
    }

    public function scopeBoardGame(Builder $query): Builder
    {
        return $query->where('type', ItemType::BoardGame->value);
    }

    public function scopeOwned(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::Owned->value);
    }

    public function scopeLoaned(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::Loaned->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::Archived->value);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
