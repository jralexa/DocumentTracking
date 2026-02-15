<?php

namespace App\Http\Controllers;

use App\DocumentWorkflowStatus;
use App\Http\Requests\AgingOverdueReportRequest;
use App\Http\Requests\CustodyReportRequest;
use App\Http\Requests\PerformanceReportRequest;
use App\Http\Requests\SlaComplianceReportRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentAnalyticsReportController extends Controller
{
    /**
     * Show SLA compliance report.
     */
    public function slaCompliance(SlaComplianceReportRequest $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $departmentId = $this->resolveAuthorizedDepartmentFilter($user, $request->validated('department_id'));
        $month = $this->resolveMonth($request->validated('month') ?? null);
        [$startOfMonth, $endOfMonth] = $this->monthRange($month);
        $today = now()->startOfDay();
        $openStatuses = $this->openWorkflowStatuses();

        $completedBaseQuery = Document::query()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->when($departmentId !== null, fn ($query) => $this->applyCurrentDepartmentFilter($query, $departmentId));

        $completedWithinSlaQuery = (clone $completedBaseQuery)
            ->whereNotNull('due_at')
            ->whereColumn('completed_at', '<=', 'due_at');

        $completedBreachedQuery = (clone $completedBaseQuery)
            ->whereNotNull('due_at')
            ->whereColumn('completed_at', '>', 'due_at');

        $completedWithoutDueDateQuery = (clone $completedBaseQuery)
            ->whereNull('due_at');

        $openPastDueQuery = Document::query()
            ->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', $today)
            ->when($departmentId !== null, fn ($query) => $this->applyCurrentDepartmentFilter($query, $departmentId));

        $completedWithinSla = (clone $completedWithinSlaQuery)->count();
        $completedBreached = (clone $completedBreachedQuery)->count();
        $completedWithoutDueDate = (clone $completedWithoutDueDateQuery)->count();
        $measuredClosedTotal = $completedWithinSla + $completedBreached;

        $metrics = [
            'closed_total' => (clone $completedBaseQuery)->count(),
            'measured_closed_total' => $measuredClosedTotal,
            'completed_within_sla' => $completedWithinSla,
            'completed_breached' => $completedBreached,
            'completed_without_due_date' => $completedWithoutDueDate,
            'open_past_due' => (clone $openPastDueQuery)->count(),
            'compliance_rate' => $measuredClosedTotal === 0
                ? null
                : round(($completedWithinSla / $measuredClosedTotal) * 100, 2),
        ];

        $breachedCompletedDocuments = (clone $completedBreachedQuery)
            ->with(['currentDepartment', 'currentUser'])
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();

        $openPastDueDocuments = (clone $openPastDueQuery)
            ->with(['currentDepartment', 'currentUser'])
            ->orderBy('due_at')
            ->limit(20)
            ->get();

        $byDocumentType = $this->slaBreakdownByGroup(
            departmentId: $departmentId,
            startOfMonth: $startOfMonth,
            endOfMonth: $endOfMonth,
            groupColumn: 'document_type'
        );

        $byPriority = $this->slaBreakdownByGroup(
            departmentId: $departmentId,
            startOfMonth: $startOfMonth,
            endOfMonth: $endOfMonth,
            groupColumn: 'priority'
        );

        return view('reports.sla-compliance', [
            'metrics' => $metrics,
            'breachedCompletedDocuments' => $breachedCompletedDocuments,
            'openPastDueDocuments' => $openPastDueDocuments,
            'byDocumentType' => $byDocumentType,
            'byPriority' => $byPriority,
            'activeDepartments' => $this->activeDepartments($user),
            'canViewAllDepartments' => $this->canViewAllDepartmentReports($user),
            'selectedMonth' => $month->format('Y-m'),
            'filters' => [
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Show aging and overdue report.
     */
    public function aging(AgingOverdueReportRequest $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $departmentId = $this->resolveAuthorizedDepartmentFilter($user, $request->validated('department_id'));
        $overdueDays = (int) ($request->validated('overdue_days') ?? 5);
        $today = now()->startOfDay();
        $agingDate = now()->subDays($overdueDays);
        $openStatuses = $this->openWorkflowStatuses();

        $baseQuery = Document::query()
            ->whereIn('status', $openStatuses)
            ->when($departmentId !== null, fn ($query) => $this->applyCurrentDepartmentFilter($query, $departmentId));

        $metrics = [
            'open_total' => (clone $baseQuery)->count(),
            'with_due_date' => (clone $baseQuery)->whereNotNull('due_at')->count(),
            'overdue_count' => (clone $baseQuery)->whereNotNull('due_at')->whereDate('due_at', '<', $today)->count(),
            'aging_count' => (clone $baseQuery)->where('updated_at', '<=', $agingDate)->count(),
        ];

        $overdueDocuments = Document::query()
            ->with(['currentDepartment', 'currentUser'])
            ->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', $today)
            ->when($departmentId !== null, fn ($query) => $this->applyCurrentDepartmentFilter($query, $departmentId))
            ->orderBy('due_at')
            ->paginate(15)
            ->withQueryString();

        $activeDepartments = $this->activeDepartments($user);

        return view('reports.aging-overdue', [
            'metrics' => $metrics,
            'overdueDocuments' => $overdueDocuments,
            'activeDepartments' => $activeDepartments,
            'canViewAllDepartments' => $this->canViewAllDepartmentReports($user),
            'filters' => [
                'department_id' => $departmentId,
                'overdue_days' => $overdueDays,
            ],
        ]);
    }

    /**
     * Show department and user performance report.
     */
    public function performance(PerformanceReportRequest $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $month = $this->resolveMonth($request->validated('month') ?? null);
        $departmentId = $this->resolveAuthorizedDepartmentFilter($user, $request->validated('department_id'));
        [$startOfMonth, $endOfMonth] = $this->monthRange($month);

        $forwardedQuery = DocumentTransfer::query()
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', [TransferStatus::Recalled->value, TransferStatus::Cancelled->value])
            ->when($departmentId !== null, fn ($query) => $query->where('from_department_id', (int) $departmentId));

        $receivedQuery = DocumentTransfer::query()
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->when($departmentId !== null, fn ($query) => $query->where('to_department_id', (int) $departmentId));

        $acceptedTransfers = DocumentTransfer::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$startOfMonth, $endOfMonth])
            ->when($departmentId !== null, fn ($query) => $query->where('to_department_id', (int) $departmentId))
            ->get(['forwarded_at', 'accepted_at']);

        $averageAcceptanceSeconds = (float) ($acceptedTransfers
            ->map(static fn (DocumentTransfer $transfer): ?int => $transfer->accepted_at?->diffInSeconds($transfer->forwarded_at))
            ->filter(static fn (?int $seconds): bool => $seconds !== null)
            ->avg() ?? 0);

        $departmentPerformance = DocumentTransfer::query()
            ->selectRaw('departments.id as department_id, departments.name as department_name, COUNT(*) as processed_count')
            ->join('departments', 'departments.id', '=', 'document_transfers.from_department_id')
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', [TransferStatus::Recalled->value, TransferStatus::Cancelled->value])
            ->when($departmentId !== null, fn ($query) => $query->where('from_department_id', (int) $departmentId))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('processed_count')
            ->get();

        $userPerformance = DocumentTransfer::query()
            ->selectRaw('users.id as user_id, users.name as user_name, COUNT(*) as forwarded_count')
            ->join('users', 'users.id', '=', 'document_transfers.forwarded_by_user_id')
            ->whereBetween('forwarded_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', [TransferStatus::Recalled->value, TransferStatus::Cancelled->value])
            ->when($departmentId !== null, fn ($query) => $query->where('from_department_id', (int) $departmentId))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('forwarded_count')
            ->limit(20)
            ->get();

        $metrics = [
            'forwarded_count' => (clone $forwardedQuery)->count(),
            'received_count' => (clone $receivedQuery)->count(),
            'accepted_count' => $acceptedTransfers->count(),
            'average_acceptance_hours' => round($averageAcceptanceSeconds / 3600, 2),
        ];

        $activeDepartments = $this->activeDepartments($user);

        return view('reports.performance', [
            'metrics' => $metrics,
            'departmentPerformance' => $departmentPerformance,
            'userPerformance' => $userPerformance,
            'activeDepartments' => $activeDepartments,
            'canViewAllDepartments' => $this->canViewAllDepartmentReports($user),
            'selectedMonth' => $month->format('Y-m'),
            'filters' => [
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Show custody report summary and returnable visibility.
     */
    public function custody(CustodyReportRequest $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $departmentId = $this->resolveAuthorizedDepartmentFilter($user, $request->validated('department_id'));
        $today = now()->toDateString();

        $currentOriginalsQuery = DocumentCustody::query()
            ->current()
            ->original()
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', (int) $departmentId));

        $activeCopiesQuery = DocumentCopy::query()
            ->where('is_discarded', false)
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', (int) $departmentId));

        $returnableQuery = Document::query()
            ->where('is_returnable', true)
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $departmentId = (int) $departmentId;

                $query->where(function ($departmentQuery) use ($departmentId) {
                    $departmentQuery
                        ->where('original_current_department_id', $departmentId)
                        ->orWhere(function ($fallbackQuery) use ($departmentId) {
                            $fallbackQuery
                                ->whereNull('original_current_department_id')
                                ->where('current_department_id', $departmentId);
                        });
                });
            });

        $metrics = [
            'current_originals' => (clone $currentOriginalsQuery)->count(),
            'active_copies' => (clone $activeCopiesQuery)->count(),
            'returnable_pending' => (clone $returnableQuery)->whereNull('returned_at')->count(),
            'returnable_overdue' => (clone $returnableQuery)->whereNull('returned_at')->whereDate('return_deadline', '<', $today)->count(),
            'returnable_returned' => (clone $returnableQuery)->whereNotNull('returned_at')->count(),
        ];

        $latestOriginalCustodies = (clone $currentOriginalsQuery)
            ->with(['document', 'department', 'user'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $latestCopies = (clone $activeCopiesQuery)
            ->with(['document', 'department', 'user'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $overdueReturnables = (clone $returnableQuery)
            ->with(['originalCurrentDepartment', 'originalCustodian'])
            ->whereNull('returned_at')
            ->whereDate('return_deadline', '<', $today)
            ->orderBy('return_deadline')
            ->limit(20)
            ->get();

        $activeDepartments = $this->activeDepartments($user);

        return view('reports.custody', [
            'metrics' => $metrics,
            'latestOriginalCustodies' => $latestOriginalCustodies,
            'latestCopies' => $latestCopies,
            'overdueReturnables' => $overdueReturnables,
            'activeDepartments' => $activeDepartments,
            'canViewAllDepartments' => $this->canViewAllDepartmentReports($user),
            'filters' => [
                'department_id' => $departmentId,
            ],
        ]);
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
     * Get open workflow statuses considered in aging metrics.
     *
     * @return array<int, string>
     */
    protected function openWorkflowStatuses(): array
    {
        return [
            DocumentWorkflowStatus::Incoming->value,
            DocumentWorkflowStatus::OnQueue->value,
            DocumentWorkflowStatus::Outgoing->value,
        ];
    }

    /**
     * Resolve month date range boundaries.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function monthRange(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        ];
    }

    /**
     * Get active departments for filter dropdowns.
     *
     * @return Collection<int, Department>
     */
    protected function activeDepartments(User $user): Collection
    {
        $query = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->select(['id', 'name']);

        if ($this->canViewAllDepartmentReports($user)) {
            return $query->get();
        }

        if ($user->department_id === null) {
            return collect();
        }

        return $query
            ->whereKey($user->department_id)
            ->get();
    }

    /**
     * Apply current department filter to document-based queries.
     */
    protected function applyCurrentDepartmentFilter(Builder $query, int|string $departmentId): Builder
    {
        return $query->where('current_department_id', (int) $departmentId);
    }

    /**
     * Build SLA breakdown metrics grouped by a document attribute.
     *
     * @return Collection<int, object{group_value:string|null, measured_closed_total:int, completed_within_sla:int, completed_breached:int, compliance_rate:float|null}>
     */
    protected function slaBreakdownByGroup(?int $departmentId, Carbon $startOfMonth, Carbon $endOfMonth, string $groupColumn): Collection
    {
        return Document::query()
            ->selectRaw($groupColumn.' as group_value')
            ->selectRaw('SUM(CASE WHEN due_at IS NOT NULL THEN 1 ELSE 0 END) as measured_closed_total')
            ->selectRaw('SUM(CASE WHEN due_at IS NOT NULL AND completed_at <= due_at THEN 1 ELSE 0 END) as completed_within_sla')
            ->selectRaw('SUM(CASE WHEN due_at IS NOT NULL AND completed_at > due_at THEN 1 ELSE 0 END) as completed_breached')
            ->selectRaw('ROUND(100 * SUM(CASE WHEN due_at IS NOT NULL AND completed_at <= due_at THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN due_at IS NOT NULL THEN 1 ELSE 0 END), 0), 2) as compliance_rate')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->when($departmentId !== null, fn ($query) => $this->applyCurrentDepartmentFilter($query, $departmentId))
            ->groupBy($groupColumn)
            ->orderByDesc('measured_closed_total')
            ->orderBy($groupColumn)
            ->get();
    }

    /**
     * Resolve the current authenticated user.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Resolve department filter based on report access scope.
     */
    protected function resolveAuthorizedDepartmentFilter(User $user, ?int $departmentId): ?int
    {
        if ($this->canViewAllDepartmentReports($user)) {
            return $departmentId;
        }

        if ($user->department_id === null) {
            abort(403, 'Manager account must be assigned to a department.');
        }

        if ($departmentId !== null && $departmentId !== $user->department_id) {
            abort(403, 'You are not authorized to view reports for other departments.');
        }

        return $user->department_id;
    }

    /**
     * Determine whether user can report on all departments.
     */
    protected function canViewAllDepartmentReports(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
