<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemLoan>
 */
class ItemLoanFactory extends Factory
{
    protected $model = ItemLoan::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'borrower_name' => fake()->name(),
            'borrower_contact' => fake()->optional()->safeEmail(),
            'loaned_at' => now()->subDays(fake()->numberBetween(1, 14)),
            'expected_return_at' => fake()->optional()->dateTimeBetween('now', '+21 days'),
            'returned_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'returned_at' => null,
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'returned_at' => now()->subDay(),
        ]);
    }
}
