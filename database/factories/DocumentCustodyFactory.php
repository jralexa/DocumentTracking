<?php

namespace Database\Factories;

use App\DocumentVersionType;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCustody>
 */
class DocumentCustodyFactory extends Factory
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
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'version_type' => fake()->randomElement([
                DocumentVersionType::Original,
                DocumentVersionType::CertifiedCopy,
                DocumentVersionType::Photocopy,
                DocumentVersionType::Scan,
            ]),
            'is_current' => true,
            'status' => fake()->randomElement(['in_custody', 'forwarded', 'returned']),
            'physical_location' => fake()->optional()->randomElement(['Cabinet A', 'Cabinet B', 'Desk Drawer 2']),
            'storage_reference' => fake()->optional()->bothify('FILE-####'),
            'purpose' => fake()->optional()->sentence(),
            'received_at' => now()->subDays(fake()->numberBetween(0, 10)),
            'released_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
