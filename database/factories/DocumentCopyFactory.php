<?php

namespace Database\Factories;

use App\DocumentVersionType;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCopy>
 */
class DocumentCopyFactory extends Factory
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
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'copy_type' => DocumentVersionType::Photocopy,
            'storage_location' => fake()->optional()->bothify('Cabinet ??-#'),
            'purpose' => fake()->optional()->sentence(),
            'recorded_at' => now(),
            'is_discarded' => false,
            'discarded_at' => null,
        ];
    }
}
