<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentMonthlyReportRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentMonthlyReportService;
use App\UserRole;
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
        $user = $this->resolveAuthenticatedUser($request);
        $departments = $this->availableDepartments($user);
        $selectedMonth = $this->resolveMonth($request->validated('month') ?? null);
        $selectedDepartment = $this->resolveDepartment($departments, $request->validated('department_id') ?? null);

        abort_if($selectedDepartment === null, 403, 'No authorized department available for reporting.');

        $report = $this->reportService->buildReport($selectedDepartment, $selectedMonth);

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
        $user = $this->resolveAuthenticatedUser($request);
        $departments = $this->availableDepartments($user);
        $selectedDepartment = $this->resolveDepartment($departments, $request->validated('department_id') ?? null);

        abort_if($selectedDepartment === null, 403, 'No authorized department available for export.');

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
    protected function availableDepartments(User $user): Collection
    {
        if ($this->canViewAllDepartmentReports($user)) {
            return Department::query()->orderBy('name')->get();
        }

        if ($user->department_id === null) {
            return collect();
        }

        return Department::query()
            ->whereKey($user->department_id)
            ->orderBy('name')
            ->get();
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
            $selectedDepartment = $departments->firstWhere('id', $departmentId);
            abort_if($selectedDepartment === null, 403, 'You are not authorized to generate reports for that department.');

            return $selectedDepartment;
        }

        return $departments->first();
    }

    /**
     * Resolve the current authenticated user.
     */
    protected function resolveAuthenticatedUser(DepartmentMonthlyReportRequest $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Determine whether user can report on all departments.
     */
    protected function canViewAllDepartmentReports(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
