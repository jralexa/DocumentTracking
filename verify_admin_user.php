<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = Illuminate\Support\Facades\DB::table('documents')
    ->whereNotNull('returned_at')
    ->where('status', '!=', 'finished')
    ->update([
        'status' => 'finished',
        'completed_at' => Illuminate\Support\Facades\DB::raw('COALESCE(completed_at, returned_at)'),
        'current_department_id' => null,
        'current_user_id' => null,
        'updated_at' => now(),
    ]);

echo 'backfilled_documents='.$count.PHP_EOL;
