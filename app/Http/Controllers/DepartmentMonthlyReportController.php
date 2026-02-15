<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentMonthlyReportRequest;
use App\Models\Department;
use App\Services\DepartmentMonthlyReportService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DepartmentMonthlyReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DepartmentMonthlyReportService $reportService) {}

    /**
     * Show monthly report dashboard for departments.
     */
    public function index(DepartmentMonthlyReportRequest $request): View
    {
        $departments = $this->availableDepartments();
        $selectedMonth = $this->resolveMonth($request->validated('month') ?? null);
        $selectedDepartment = $this->resolveDepartment($departments, $request->validated('department_id') ?? null);

        $report = $selectedDepartment === null
            ? null
            : $this->reportService->buildReport($selectedDepartment, $selectedMonth);

        return view('reports.departments.monthly', [
            'departments' => $departments,
            'selectedDepartment' => $selectedDepartment,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'report' => $report,
        ]);
    }

    /**
     * Export monthly report as CSV.
     */
    public function export(DepartmentMonthlyReportRequest $request): Response
    {
        $departments = $this->availableDepartments();
        $selectedDepartment = $this->resolveDepartment($departments, $request->validated('department_id') ?? null);

        abort_if($selectedDepartment === null, 404, 'No department available for export.');

        $selectedMonth = $this->resolveMonth($request->validated('month') ?? null);

        return $this->reportService->downloadCsv($selectedDepartment, $selectedMonth);
    }

    /**
     * Resolve selected month value.
     */
    protected function resolveMonth(?string $month): Carbon
    {
        if ($month === null || $month === '') {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    }

    /**
     * Get departments available to reporting screens.
     *
     * @return Collection<int, Department>
     */
    protected function availableDepartments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * Resolve selected department from available collection.
     *
     * @param  Collection<int, Department>  $departments
     */
    protected function resolveDepartment(Collection $departments, ?int $departmentId): ?Department
    {
        if ($departments->isEmpty()) {
            return null;
        }

        if ($departmentId !== null) {
            return $departments->firstWhere('id', $departmentId);
        }

        return $departments->first();
    }
}
