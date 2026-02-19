<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCase>
 */
class DocumentCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ownerType = fake()->randomElement(['district', 'school', 'personal', 'others']);

        return [
            'case_number' => 'CASE-'.strtoupper(Str::random(10)),
            'title' => fake()->sentence(6),
            'owner_type' => $ownerType,
            'owner_name' => fake()->name(),
            'owner_reference' => fake()->optional()->bothify('REF-####'),
            'opened_by_user_id' => null,
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['open', 'on_hold', 'closed']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'opened_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'closed_at' => null,
        ];
    }
}
