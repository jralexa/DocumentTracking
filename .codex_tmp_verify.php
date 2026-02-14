<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::query()->where('email', 'admin@example.com')->with('department')->first();
if (! $admin) {
    echo "NO_ADMIN\n";
    exit;
}

echo "ADMIN: {$admin->name} | dept_id={$admin->department_id}\n";
if ($admin->department) {
    echo "ADMIN_DEPT: {$admin->department->id}|{$admin->department->code}|{$admin->department->name}|active=".(int)$admin->department->is_active."\n";
}

echo "DEPARTMENTS:\n";
foreach (App\Models\Department::query()->orderBy('name')->get(['id','code','name','is_active']) as $d) {
    echo "{$d->id}|{$d->code}|{$d->name}|active=".(int)$d->is_active."\n";
}

echo "ON_QUEUE_DOCS:\n";
foreach (App\Models\Document::query()->forOnQueue($admin)->get(['id','tracking_number','subject','current_department_id','current_user_id']) as $doc) {
    echo "{$doc->id}|{$doc->tracking_number}|{$doc->subject}|dept={$doc->current_department_id}|user={$doc->current_user_id}\n";
}
