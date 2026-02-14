<?php

use App\Jobs\GenerateDepartmentMonthlyReportsJob;
use App\Jobs\GenerateDocumentAlertsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDepartmentMonthlyReportsJob)->monthlyOn(1, '01:00');
Schedule::job(new GenerateDocumentAlertsJob)->dailyAt('06:00');
