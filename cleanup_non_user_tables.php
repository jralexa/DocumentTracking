<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = collect(Illuminate\Support\Facades\DB::select('SHOW TABLES'))
    ->map(fn ($row) => array_values((array) $row)[0])
    ->filter(fn ($table) => ! in_array($table, ['users'], true))
    ->values();

Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) {
    Illuminate\Support\Facades\DB::table($table)->truncate();
}
Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo 'Truncated: '.$tables->count().' tables (excluded: users)'.PHP_EOL;
