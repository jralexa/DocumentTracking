<?php

namespace App\Jobs;

use App\Models\Department;
use App\Services\DepartmentMonthlyReportService;
use App\Services\SystemLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GenerateDepartmentMonthlyReportsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?string $month = null) {}

    /**
     * Execute the job.
     */
    public function handle(DepartmentMonthlyReportService $reportService, ?SystemLogService $systemLogService = null): void
    {
        $resolvedSystemLogService = $systemLogService ?? app(SystemLogService::class);
        $targetMonth = $this->month === null
            ? now()->subMonthNoOverflow()->startOfMonth()
            : Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        $departmentCount = 0;

        Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(function (Department $department) use ($reportService, $targetMonth, &$departmentCount): void {
                $reportService->storeCsvReport($department, $targetMonth);
                $departmentCount++;
            });

        $resolvedSystemLogService->scheduler(
            action: 'monthly_reports_generated',
            message: 'Monthly department reports generated.',
            context: [
                'month' => $targetMonth->format('Y-m'),
                'department_count' => $departmentCount,
            ]
        );
    }
}
