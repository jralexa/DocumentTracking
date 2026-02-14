<?php

namespace Database\Factories;

use App\DocumentEventType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentEvent>
 */
class DocumentEventFactory extends Factory
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
            'document_custody_id' => null,
            'document_relationship_id' => null,
            'acted_by_user_id' => User::factory(),
            'event_type' => fake()->randomElement(DocumentEventType::cases()),
            'context' => fake()->randomElement(['general', 'workflow', 'custody', 'relationship']),
            'message' => fake()->optional()->sentence(),
            'payload' => fake()->optional()->randomElement([
                ['key' => 'value'],
                ['reason' => 'system update'],
            ]),
            'occurred_at' => now(),
        ];
    }
}
