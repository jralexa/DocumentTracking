<?php

namespace App\Jobs;

use App\Services\DocumentAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDocumentAlertsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentAlertService $alertService): void
    {
        $alertService->generateAlerts();
    }
}
