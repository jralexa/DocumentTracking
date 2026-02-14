<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\TransferStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentTransfer>
 */
class DocumentTransferFactory extends Factory
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
            'from_department_id' => Department::factory(),
            'to_department_id' => Department::factory(),
            'forwarded_by_user_id' => User::factory(),
            'accepted_by_user_id' => null,
            'status' => TransferStatus::Pending,
            'remarks' => fake()->optional()->sentence(),
            'forwarded_at' => now(),
            'accepted_at' => null,
            'recalled_at' => null,
            'recalled_by_user_id' => null,
        ];
    }
}
