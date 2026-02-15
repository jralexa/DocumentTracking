<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DistrictSchoolSeeder::class,
        ]);

        $recordsDepartment = Department::query()->where('code', 'RECORDS')->first();

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => 'password',
                'role' => UserRole::Admin,
                'department_id' => $recordsDepartment?->id,
            ]
        );
    }
}
