<?php

namespace App\Jobs;

use App\Services\DocumentAlertService;
use App\Services\SystemLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDocumentAlertsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(DocumentAlertService $alertService, ?SystemLogService $systemLogService = null): void
    {
        $resolvedSystemLogService = $systemLogService ?? app(SystemLogService::class);
        $result = $alertService->generateAlerts();

        $resolvedSystemLogService->alert(
            action: 'document_alerts_generated',
            message: 'Document alerts synchronization completed.',
            context: $result
        );
    }
}
