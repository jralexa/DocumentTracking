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
            ['code' => 'SUPERINTENDENT', 'name' => "Superintendent's Office", 'abbreviation' => 'SDO'],
            ['code' => 'ASDS', 'name' => 'Assistant Schools Division Superintendent Office', 'abbreviation' => 'ASDS'],
            ['code' => 'BUDGET', 'name' => 'Budget Office', 'abbreviation' => 'BUD'],
            ['code' => 'ACCOUNTING', 'name' => 'Accounting Office', 'abbreviation' => 'ACC'],
            ['code' => 'CASHIER', 'name' => "Cashier's Office", 'abbreviation' => 'CASH'],
            ['code' => 'HRMO', 'name' => 'Human Resource Management Office', 'abbreviation' => 'HRMO'],
            ['code' => 'ICT', 'name' => 'Information and Communications Technology Unit', 'abbreviation' => 'ICT'],
            ['code' => 'PAYROLL', 'name' => 'Payroll Services', 'abbreviation' => 'PAY'],
            ['code' => 'RECORDS', 'name' => 'Records Section', 'abbreviation' => 'REC'],
            ['code' => 'PLANNING', 'name' => 'Planning Office', 'abbreviation' => 'PLAN'],
            ['code' => 'LEGAL', 'name' => 'Legal Unit', 'abbreviation' => 'LEGAL'],
            ['code' => 'SGOD', 'name' => 'School Governance and Operations Division', 'abbreviation' => 'SGOD'],
            ['code' => 'CID', 'name' => 'Curriculum Implementation Division', 'abbreviation' => 'CID'],
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
