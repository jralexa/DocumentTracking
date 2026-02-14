<?php

namespace Database\Factories;

use App\DocumentAlertType;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAlert>
 */
class DocumentAlertFactory extends Factory
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
            'alert_type' => fake()->randomElement(DocumentAlertType::cases()),
            'severity' => fake()->randomElement(['warning', 'critical']),
            'message' => fake()->sentence(),
            'metadata' => [
                'source' => 'scheduler',
            ],
            'is_active' => true,
            'triggered_at' => now(),
            'resolved_at' => null,
        ];
    }
}
