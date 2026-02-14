<?php

namespace App\Jobs;

use App\Models\Department;
use App\Services\DepartmentMonthlyReportService;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDepartmentMonthlyReportsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?string $month = null)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(DepartmentMonthlyReportService $reportService): void
    {
        $targetMonth = $this->month === null
            ? now()->subMonthNoOverflow()->startOfMonth()
            : Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(function (Department $department) use ($reportService, $targetMonth): void {
                $reportService->storeCsvReport($department, $targetMonth);
            });
    }
}
