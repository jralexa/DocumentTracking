<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistrictSchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datasetPath = database_path('seeders/data/district_school_pairs.json');

        if (! file_exists($datasetPath)) {
            return;
        }

        /** @var array<int, array{district:string,school:string}> $pairs */
        $pairs = json_decode((string) file_get_contents($datasetPath), true, 512, JSON_THROW_ON_ERROR);
        $districtCodeCounters = [];
        $districtIdByName = [];

        foreach ($pairs as $pair) {
            $districtName = trim($pair['district']);

            if (! array_key_exists($districtName, $districtIdByName)) {
                $baseCode = strtoupper((string) Str::of($districtName)->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_'));
                $districtCodeCounters[$baseCode] = ($districtCodeCounters[$baseCode] ?? 0) + 1;
                $districtCode = $districtCodeCounters[$baseCode] === 1
                    ? $baseCode
                    : $baseCode.'_'.$districtCodeCounters[$baseCode];

                $district = District::query()->updateOrCreate(
                    ['name' => $districtName],
                    [
                        'code' => $districtCode,
                        'is_active' => true,
                    ]
                );

                $districtIdByName[$districtName] = $district->id;
            }

            School::query()->updateOrCreate(
                [
                    'district_id' => $districtIdByName[$districtName],
                    'name' => trim($pair['school']),
                ],
                [
                    'code' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
