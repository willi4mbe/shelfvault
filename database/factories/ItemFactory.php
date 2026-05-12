<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'type' => ItemType::Film->value,
            'title' => fake()->sentence(3),
            'original_title' => fake()->optional()->sentence(3),
            'sort_title' => fake()->optional()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'release_year' => fake()->optional()->numberBetween(1950, (int) date('Y')),
            'barcode' => fake()->optional()->ean13(),
            'cover_path' => fake()->optional()->imageUrl(640, 960, 'covers', true),
            'physical_format' => fake()->optional()->randomElement([
                'dvd',
                'blu_ray',
                '4k_uhd',
                'vhs',
                'digital_copy',
                'cartridge',
                'disc',
                'code_in_box',
                'collector_edition',
                'box',
                'expansion',
                'card_game',
                'accessory',
            ]),
            'edition' => fake()->optional()->word(),
            'region' => fake()->optional()->randomElement(['A', 'B', 'C', 'NTSC', 'PAL', 'NTSC-J']),
            'condition' => ItemCondition::VeryGood->value,
            'location' => fake()->optional()->words(2, true),
            'status' => ItemStatus::Owned->value,
            'is_favorite' => fake()->boolean(),
            'personal_notes' => fake()->optional()->paragraph(),
            'acquired_at' => fake()->optional()->date(),
        ];
    }

    public function film(): static
    {
        return $this->state(fn () => [
            'type' => ItemType::Film->value,
            'runtime_minutes' => fake()->numberBetween(60, 240),
            'director' => fake()->name(),
            'cast_members' => [fake()->name(), fake()->name()],
            'genres' => ['drama', 'action'],
            'studio' => fake()->company(),
            'age_rating' => fake()->randomElement(['G', 'PG', 'PG-13', 'R', '18']),
            'external_tmdb_id' => fake()->numerify('tmdb-#####'),
            'platform' => null,
            'developer' => null,
            'publisher' => null,
            'modes' => null,
            'external_igdb_id' => null,
            'min_players' => null,
            'max_players' => null,
            'play_time_minutes' => null,
            'designer' => null,
        ]);
    }

    public function videoGame(): static
    {
        return $this->state(fn () => [
            'type' => ItemType::VideoGame->value,
            'runtime_minutes' => null,
            'director' => null,
            'cast_members' => null,
            'genres' => ['rpg', 'adventure'],
            'studio' => null,
            'age_rating' => fake()->randomElement(['E', 'E10+', 'T', 'M', 'PEGI 12', 'PEGI 16']),
            'external_tmdb_id' => null,
            'platform' => fake()->randomElement(['PlayStation 5', 'Nintendo Switch', 'Xbox Series X', 'PC']),
            'developer' => fake()->company(),
            'publisher' => fake()->company(),
            'modes' => ['single_player', 'local_multiplayer'],
            'external_igdb_id' => fake()->numerify('igdb-#####'),
            'min_players' => null,
            'max_players' => null,
            'play_time_minutes' => null,
            'designer' => null,
        ]);
    }

    public function boardGame(): static
    {
        return $this->state(fn () => [
            'type' => ItemType::BoardGame->value,
            'runtime_minutes' => null,
            'director' => null,
            'cast_members' => null,
            'genres' => ['strategy', 'family'],
            'studio' => null,
            'age_rating' => fake()->randomElement(['3+', '7+', '10+', '12+', '16+']),
            'external_tmdb_id' => null,
            'platform' => null,
            'developer' => null,
            'publisher' => fake()->company(),
            'modes' => null,
            'external_igdb_id' => null,
            'min_players' => fake()->numberBetween(1, 4),
            'max_players' => fake()->numberBetween(4, 8),
            'play_time_minutes' => fake()->numberBetween(15, 180),
            'designer' => fake()->name(),
        ]);
    }

    public function owned(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::Owned->value,
        ]);
    }

    public function loaned(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::Loaned->value,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::Archived->value,
        ]);
    }

    public function wanted(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::Wanted->value,
        ]);
    }
}
