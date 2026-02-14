<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentRemark>
 */
class DocumentRemarkFactory extends Factory
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
            'document_transfer_id' => null,
            'document_item_id' => null,
            'parent_remark_id' => null,
            'user_id' => User::factory(),
            'context' => fake()->randomElement(['general', 'workflow', 'custody']),
            'remark' => fake()->sentence(),
            'is_system' => false,
            'remarked_at' => now(),
        ];
    }
}
