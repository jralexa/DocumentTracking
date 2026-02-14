<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departmentName = fake()->unique()->company();

        return [
            'code' => fake()->unique()->bothify('DEPT-###'),
            'name' => $departmentName,
            'abbreviation' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $departmentName), 0, 8)),
            'is_active' => true,
        ];
    }
}
