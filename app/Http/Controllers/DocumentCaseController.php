<?php

namespace App\Http\Controllers;

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Http\Requests\CaseTimelineFilterRequest;
use App\Models\DocumentCase;
use App\Models\DocumentEvent;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentCaseController extends Controller
{
    /**
     * Display case listing.
     */
    public function index(Request $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $cases = $this->cases($user);

        return view('cases.index', [
            'cases' => $cases,
        ]);
    }

    /**
     * Show a specific case and linked documents.
     */
    public function show(CaseTimelineFilterRequest $request, DocumentCase $documentCase): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $this->ensureCaseVisibleToUser($documentCase, $user);
        $this->loadCaseDocuments($documentCase);
        $statusSummary = $this->statusSummary($documentCase->documents);
        $departmentSummary = $this->departmentSummary($documentCase->documents);
        $caseMetrics = $this->caseMetrics($documentCase->documents);
        $timelineFilters = $request->validated();
        $timelineEvents = $this->timelineEvents($documentCase, $timelineFilters);

        return view('cases.show', [
            'documentCase' => $documentCase,
            'statusSummary' => $statusSummary,
            'departmentSummary' => $departmentSummary,
            'caseMetrics' => $caseMetrics,
            'timelineEvents' => $timelineEvents,
            'timelineFilters' => [
                'event_type' => $timelineFilters['event_type'] ?? null,
                'tracking_number' => $timelineFilters['tracking_number'] ?? null,
                'from_date' => $timelineFilters['from_date'] ?? null,
                'to_date' => $timelineFilters['to_date'] ?? null,
            ],
            'timelineEventTypes' => array_map(
                static fn (DocumentEventType $eventType): string => $eventType->value,
                DocumentEventType::cases()
            ),
        ]);
    }

    /**
     * Close an open case when all linked documents are finished.
     */
    public function close(Request $request, DocumentCase $documentCase): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        abort_unless($user->canManageDocuments(), 403);

        if ($documentCase->status === 'closed') {
            return back()->with('status', 'Case is already closed.');
        }

        $hasOpenDocuments = $documentCase->documents()
            ->where('status', '!=', DocumentWorkflowStatus::Finished->value)
            ->exists();

        if ($hasOpenDocuments) {
            return back()->withErrors([
                'case' => 'Cannot close case while there are open documents.',
            ]);
        }

        $documentCase->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('status', 'Case closed successfully.');
    }

    /**
     * Reopen a closed case to allow new linked documents.
     */
    public function reopen(Request $request, DocumentCase $documentCase): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        abort_unless($user->canManageDocuments(), 403);

        if ($documentCase->status === 'open') {
            return back()->with('status', 'Case is already open.');
        }

        $documentCase->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return back()->with('status', 'Case reopened successfully.');
    }

    /**
     * Get paginated case listing.
     */
    protected function cases(User $user): LengthAwarePaginator
    {
        return DocumentCase::query()
            ->when(
                $user->hasRole(UserRole::Guest),
                fn ($query) => $query->where('opened_by_user_id', $user->id)
            )
            ->withCount('documents')
            ->orderByDesc('opened_at')
            ->paginate(15);
    }

    /**
     * Ensure authenticated user can access the selected case.
     */
    protected function ensureCaseVisibleToUser(DocumentCase $documentCase, User $user): void
    {
        if ($user->hasRole(UserRole::Guest) && $documentCase->opened_by_user_id !== $user->id) {
            abort(403);
        }
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
     * Load case documents and related associations for case detail view.
     */
    protected function loadCaseDocuments(DocumentCase $documentCase): void
    {
        $documentCase->load([
            'documents.currentDepartment',
            'documents.currentUser',
            'documents.originalCurrentDepartment',
            'documents.latestTransfer.toDepartment',
            'documents.latestTransfer.fromDepartment',
            'documents.outgoingRelationships.relatedDocument',
            'documents.incomingRelationships.sourceDocument',
        ]);
    }

    /**
     * Build case status summary map.
     */
    protected function statusSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn ($document) => $document->status->value)
            ->map(fn ($group) => $group->count())
            ->sortKeys();
    }

    /**
     * Build case department summary map.
     */
    protected function departmentSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn ($document) => $document->currentDepartment?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->count())
            ->sortKeys();
    }

    /**
     * Build case-centric metrics for case detail monitoring.
     *
     * @return array<string, int>
     */
    protected function caseMetrics(Collection $documents): array
    {
        $today = now()->toDateString();

        return [
            'total_documents' => $documents->count(),
            'open_documents' => $documents->filter(
                static fn ($document): bool => $document->status !== DocumentWorkflowStatus::Finished
            )->count(),
            'overdue_documents' => $documents->filter(
                static fn ($document): bool => $document->status !== DocumentWorkflowStatus::Finished
                    && $document->due_at !== null
                    && $document->due_at->toDateString() < $today
            )->count(),
            'returnable_pending' => $documents->filter(
                static fn ($document): bool => $document->is_returnable && $document->returned_at === null
            )->count(),
            'returned_documents' => $documents->filter(
                static fn ($document): bool => $document->returned_at !== null
            )->count(),
        ];
    }

    /**
     * Build a reverse-chronological case timeline from document events.
     *
     * @param  array{
     *   event_type?:string|null,
     *   tracking_number?:string|null,
     *   from_date?:string|null,
     *   to_date?:string|null
     * }  $filters
     * @return Collection<int, array<string, string|null>>
     */
    protected function timelineEvents(DocumentCase $documentCase, array $filters = []): Collection
    {
        $documentIds = $documentCase->documents->pluck('id');

        if ($documentIds->isEmpty()) {
            return collect();
        }

        $eventType = $filters['event_type'] ?? null;
        $trackingNumber = trim((string) ($filters['tracking_number'] ?? ''));
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        /** @var EloquentCollection<int, DocumentEvent> $events */
        $events = DocumentEvent::query()
            ->whereIn('document_id', $documentIds)
            ->when($eventType !== null && $eventType !== '', fn ($query) => $query->where('event_type', $eventType))
            ->when($fromDate !== null && $fromDate !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $fromDate))
            ->when($toDate !== null && $toDate !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $toDate))
            ->when($trackingNumber !== '', function ($query) use ($trackingNumber): void {
                $query->whereHas('document', function ($documentQuery) use ($trackingNumber): void {
                    $like = '%'.$trackingNumber.'%';

                    $documentQuery
                        ->where('tracking_number', $trackingNumber)
                        ->orWhere('metadata->display_tracking', $trackingNumber)
                        ->orWhere('tracking_number', 'like', $like)
                        ->orWhere('metadata->display_tracking', 'like', $like);
                });
            })
            ->with([
                'document:id,tracking_number,subject,metadata',
                'actedBy:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $events->map(function (DocumentEvent $event): array {
            $document = $event->document;
            $trackingNumber = $document?->metadata['display_tracking'] ?? $document?->tracking_number;

            return [
                'occurred_at' => $event->occurred_at?->format('Y-m-d H:i'),
                'event_type_label' => Str::headline((string) $event->event_type->value),
                'message' => $event->message,
                'actor' => $event->actedBy?->name ?? 'System',
                'tracking_number' => $trackingNumber,
                'subject' => $document?->subject,
            ];
        });
    }
}
