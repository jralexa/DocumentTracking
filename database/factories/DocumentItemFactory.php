<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentItem>
 */
class DocumentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'parent_item_id' => null,
            'item_code' => fake()->optional()->bothify('ITM-####'),
            'name' => fake()->sentence(4),
            'item_type' => fake()->randomElement(['main', 'attachment', 'annex']),
            'status' => fake()->randomElement(['active', 'withdrawn']),
            'quantity' => fake()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(0, 20),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
