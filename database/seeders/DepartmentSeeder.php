<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['code' => 'RECORDS', 'name' => 'Records Section', 'abbreviation' => 'REC'],
            ['code' => 'ACCOUNTING', 'name' => 'Accounting Section', 'abbreviation' => 'ACC'],
            ['code' => 'BUDGET', 'name' => 'Budget Section', 'abbreviation' => 'BUD'],
            ['code' => 'CASH', 'name' => 'Cash Section', 'abbreviation' => 'CSH'],
            ['code' => 'HR', 'name' => 'Human Resource Section', 'abbreviation' => 'HR'],
        ];

        foreach ($departments as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'abbreviation' => $department['abbreviation'],
                    'is_active' => true,
                ]
            );
        }
    }
}
