<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = Illuminate\Support\Facades\DB::table('documents')->orderByDesc('id')->first();
if ($doc) {
    echo 'tracking='.$doc->tracking_number.PHP_EOL;
    echo 'status='.$doc->status.PHP_EOL;
    echo 'current_department_id='.($doc->current_department_id ?? 'null').PHP_EOL;
    echo 'current_user_id='.($doc->current_user_id ?? 'null').PHP_EOL;
    echo 'returned_at='.($doc->returned_at ?? 'null').PHP_EOL;
    echo 'completed_at='.($doc->completed_at ?? 'null').PHP_EOL;
}
