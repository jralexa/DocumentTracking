<?php

namespace App\Http\Controllers;

use App\DocumentWorkflowStatus;
use App\Http\Requests\AgingOverdueReportRequest;
use App\Http\Requests\CustodyReportRequest;
use App\Http\Requests\PerformanceReportRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\DocumentTransfer;
use App\TransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentAnalyticsReportController extends Controller
{
    /**
     * Show aging and overdue report.
     */
    public function aging(AgingOverdueReportRequest $request): View
    {
        $departmentId = $request->validated('department_id');
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

        $activeDepartments = $this->activeDepartments();

        return view('reports.aging-overdue', [
            'metrics' => $metrics,
            'overdueDocuments' => $overdueDocuments,
            'activeDepartments' => $activeDepartments,
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
        $month = $this->resolveMonth($request->validated('month') ?? null);
        $departmentId = $request->validated('department_id');
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

        $activeDepartments = $this->activeDepartments();

        return view('reports.performance', [
            'metrics' => $metrics,
            'departmentPerformance' => $departmentPerformance,
            'userPerformance' => $userPerformance,
            'activeDepartments' => $activeDepartments,
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
        $departmentId = $request->validated('department_id');
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

        $activeDepartments = $this->activeDepartments();

        return view('reports.custody', [
            'metrics' => $metrics,
            'latestOriginalCustodies' => $latestOriginalCustodies,
            'latestCopies' => $latestCopies,
            'overdueReturnables' => $overdueReturnables,
            'activeDepartments' => $activeDepartments,
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
    protected function activeDepartments(): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Apply current department filter to document-based queries.
     */
    protected function applyCurrentDepartmentFilter(Builder $query, int|string $departmentId): Builder
    {
        return $query->where('current_department_id', (int) $departmentId);
    }
}
