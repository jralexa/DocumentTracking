<?php

namespace Database\Factories;

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\DocumentCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedAt = now()->subDays(fake()->numberBetween(0, 15));

        return [
            'document_case_id' => DocumentCase::factory(),
            'current_department_id' => Department::factory(),
            'current_user_id' => User::factory(),
            'tracking_number' => now()->format('ymd').fake()->unique()->numerify('###'),
            'reference_number' => fake()->optional()->bothify('DOC-####'),
            'subject' => fake()->sentence(8),
            'document_type' => fake()->randomElement(['communication', 'submission', 'request', 'for_processing']),
            'owner_type' => fake()->randomElement(['district', 'school', 'personal', 'others']),
            'owner_name' => fake()->name(),
            'status' => fake()->randomElement([
                DocumentWorkflowStatus::Incoming,
                DocumentWorkflowStatus::OnQueue,
                DocumentWorkflowStatus::Outgoing,
                DocumentWorkflowStatus::Finished,
            ]),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'received_at' => $receivedAt,
            'due_at' => (clone $receivedAt)->addDays(fake()->numberBetween(1, 20)),
            'completed_at' => null,
            'metadata' => [
                'source' => fake()->company(),
                'pages' => fake()->numberBetween(1, 30),
            ],
            'is_returnable' => false,
            'return_deadline' => null,
            'returned_at' => null,
            'returned_to' => null,
            'original_current_department_id' => null,
            'original_custodian_user_id' => null,
            'original_physical_location' => null,
        ];
    }
}
