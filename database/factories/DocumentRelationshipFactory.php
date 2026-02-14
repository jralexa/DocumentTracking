<?php

namespace Database\Factories;

use App\DocumentRelationshipType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentRelationship>
 */
class DocumentRelationshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_document_id' => Document::factory(),
            'related_document_id' => Document::factory(),
            'relation_type' => fake()->randomElement([
                DocumentRelationshipType::MergedInto,
                DocumentRelationshipType::SplitFrom,
                DocumentRelationshipType::AttachedTo,
                DocumentRelationshipType::RelatedTo,
            ]),
            'created_by_user_id' => User::factory(),
            'notes' => fake()->optional()->sentence(),
            'metadata' => fake()->optional()->randomElement([
                ['reason' => 'Manual relationship'],
                ['batch' => fake()->bothify('BATCH-###')],
            ]),
        ];
    }
}
