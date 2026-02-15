<?php

namespace App\Http\Controllers;

use App\DocumentWorkflowStatus;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\Services\DocumentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentAlertService $alertService) {}

    /**
     * Display the dashboard.
     */
    public function index(Request $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);

        $alertData = $this->alertService->getDashboardData($user);
        $departmentId = $user->department_id;
        $canProcessDepartmentQueues = $user->canProcessDocuments() && $departmentId !== null;

        $queueCounts = $this->emptyQueueCounts();
        $workflowHighlights = $this->emptyWorkflowHighlights();
        $custodyHighlights = $this->emptyCustodyHighlights();
        $recentTransfers = collect();

        if ($canProcessDepartmentQueues) {
            $today = now()->toDateString();
            $queueCounts = $this->queueCounts($user);
            $workflowHighlights = $this->workflowHighlights((int) $departmentId, $today);
            $custodyHighlights = $this->custodyHighlights((int) $departmentId);
            $recentTransfers = $this->recentTransfers((int) $departmentId);
        }

        return view('dashboard', [
            'alertCounts' => $alertData['counts'],
            'recentAlerts' => $alertData['recent_alerts'],
            'queueCounts' => $queueCounts,
            'workflowHighlights' => $workflowHighlights,
            'custodyHighlights' => $custodyHighlights,
            'recentTransfers' => $recentTransfers,
            'canProcessDepartmentQueues' => $canProcessDepartmentQueues,
        ]);
    }

    /**
     * Resolve authenticated user from request context.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Get default queue counters.
     *
     * @return array<string, int>
     */
    protected function emptyQueueCounts(): array
    {
        return [
            'incoming' => 0,
            'on_queue' => 0,
            'outgoing' => 0,
        ];
    }

    /**
     * Get default workflow highlight counters.
     *
     * @return array<string, int>
     */
    protected function emptyWorkflowHighlights(): array
    {
        return [
            'due_today' => 0,
            'overdue' => 0,
            'returnable_overdue' => 0,
        ];
    }

    /**
     * Get default custody highlight counters.
     *
     * @return array<string, int>
     */
    protected function emptyCustodyHighlights(): array
    {
        return [
            'originals_in_custody' => 0,
            'active_copies' => 0,
        ];
    }

    /**
     * Get queue counts for a processing user.
     *
     * @return array<string, int>
     */
    protected function queueCounts(User $user): array
    {
        return [
            'incoming' => Document::query()->forIncomingQueue($user)->count(),
            'on_queue' => Document::query()->forOnQueue($user)->count(),
            'outgoing' => Document::query()->forOutgoing($user)->count(),
        ];
    }

    /**
     * Get workflow highlight metrics for a department.
     *
     * @return array<string, int>
     */
    protected function workflowHighlights(int $departmentId, string $today): array
    {
        $openStatuses = [
            DocumentWorkflowStatus::Incoming->value,
            DocumentWorkflowStatus::OnQueue->value,
            DocumentWorkflowStatus::Outgoing->value,
        ];

        return [
            'due_today' => Document::query()
                ->whereIn('status', $openStatuses)
                ->where('current_department_id', $departmentId)
                ->whereDate('due_at', $today)
                ->count(),
            'overdue' => Document::query()
                ->whereIn('status', $openStatuses)
                ->where('current_department_id', $departmentId)
                ->whereDate('due_at', '<', $today)
                ->count(),
            'returnable_overdue' => Document::query()
                ->where('is_returnable', true)
                ->whereNull('returned_at')
                ->whereDate('return_deadline', '<', $today)
                ->where(function ($query) use ($departmentId): void {
                    $query
                        ->where('original_current_department_id', $departmentId)
                        ->orWhere(function ($fallbackQuery) use ($departmentId): void {
                            $fallbackQuery
                                ->whereNull('original_current_department_id')
                                ->where('current_department_id', $departmentId);
                        });
                })
                ->count(),
        ];
    }

    /**
     * Get custody highlight metrics for a department.
     *
     * @return array<string, int>
     */
    protected function custodyHighlights(int $departmentId): array
    {
        return [
            'originals_in_custody' => DocumentCustody::query()
                ->current()
                ->original()
                ->where('department_id', $departmentId)
                ->count(),
            'active_copies' => DocumentCopy::query()
                ->where('department_id', $departmentId)
                ->where('is_discarded', false)
                ->count(),
        ];
    }

    /**
     * Get recent inter-department transfers involving a department.
     *
     * @return Collection<int, DocumentTransfer>
     */
    protected function recentTransfers(int $departmentId): Collection
    {
        return DocumentTransfer::query()
            ->with([
                'document:id,tracking_number,subject',
                'fromDepartment:id,name',
                'toDepartment:id,name',
                'forwardedBy:id,name',
            ])
            ->where(function ($query) use ($departmentId): void {
                $query
                    ->where('from_department_id', $departmentId)
                    ->orWhere('to_department_id', $departmentId);
            })
            ->orderByDesc('forwarded_at')
            ->limit(8)
            ->get();
    }
}
