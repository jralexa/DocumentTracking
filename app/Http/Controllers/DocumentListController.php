<?php

namespace App\Http\Controllers;

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Http\Requests\UpdateManagedDocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Services\SystemLogService;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentListController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected SystemLogService $systemLogService) {}

    /**
     * Display searchable document listing.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $documentType = $request->query('document_type');
        $ownerType = $request->query('owner_type');
        $departmentId = $request->query('department_id');

        $documents = $this->documents(
            search: $search,
            status: $status,
            documentType: $documentType,
            ownerType: $ownerType,
            departmentId: $departmentId,
            user: $user
        );
        $activeDepartments = $this->activeDepartments();

        return view('documents.index', [
            'documents' => $documents,
            'activeDepartments' => $activeDepartments,
            'statuses' => $this->workflowStatuses(),
            'documentTypes' => $this->documentTypes(),
            'ownerTypes' => $this->ownerTypes(),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'document_type' => $documentType,
                'owner_type' => $ownerType,
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Show editable document metadata for management users.
     */
    public function edit(Document $document): View
    {
        return view('documents.edit', [
            'document' => $document,
            'documentTypes' => $this->documentTypes(),
            'ownerTypes' => $this->ownerTypes(),
            'priorities' => ['low', 'normal', 'high', 'urgent'],
        ]);
    }

    /**
     * Update managed document metadata.
     */
    public function update(UpdateManagedDocumentRequest $request, Document $document): RedirectResponse
    {
        $payload = $this->managedDocumentPayload($request->validated());
        $document->update($payload);

        $this->systemLogService->admin(
            action: 'document_metadata_updated',
            message: 'Management user updated document metadata.',
            user: $request->user(),
            request: $request,
            entity: $document,
            context: [
                'fields' => array_keys($payload),
                'tracking' => $document->metadata['display_tracking'] ?? $document->tracking_number,
            ]
        );

        return redirect()
            ->route('documents.index')
            ->with('status', 'Document updated successfully.');
    }

    /**
     * Delete a document from management view.
     */
    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $trackingNumber = $document->metadata['display_tracking'] ?? $document->tracking_number;
        $document->delete();

        $this->systemLogService->admin(
            action: 'document_deleted',
            message: 'Management user deleted a document.',
            user: $request->user(),
            request: $request,
            context: [
                'tracking' => $trackingNumber,
                'document_id' => $document->id,
            ]
        );

        return redirect()
            ->route('documents.index')
            ->with('status', sprintf('Document %s deleted.', $trackingNumber));
    }

    /**
     * Build document list query and return paginated records.
     */
    protected function documents(
        string $search,
        mixed $status,
        mixed $documentType,
        mixed $ownerType,
        mixed $departmentId,
        User $user
    ): LengthAwarePaginator {
        return Document::query()
            ->with(['documentCase', 'currentDepartment', 'currentUser', 'latestTransfer.toDepartment'])
            ->when($user->hasRole(UserRole::Guest), function (Builder $query) use ($user): void {
                $query->whereHas('events', function (Builder $eventQuery) use ($user): void {
                    $eventQuery
                        ->where('event_type', DocumentEventType::DocumentCreated->value)
                        ->where('acted_by_user_id', $user->id);
                });
            })
            ->when($search !== '', fn (Builder $query) => $this->applySearchFilter($query, $search))
            ->when($this->hasFilter($status), fn (Builder $query) => $query->where('status', $status))
            ->when($this->hasFilter($documentType), fn (Builder $query) => $query->where('document_type', $documentType))
            ->when($this->hasFilter($ownerType), fn (Builder $query) => $query->where('owner_type', $ownerType))
            ->when($this->hasFilter($departmentId), fn (Builder $query) => $query->where('current_department_id', (int) $departmentId))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Apply search filter against document fields and case fields.
     */
    protected function applySearchFilter(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(function (Builder $searchQuery) use ($search, $like): void {
            $searchQuery
                ->where('tracking_number', $search)
                ->orWhere('subject', 'like', $like)
                ->orWhere('owner_name', 'like', $like)
                ->orWhere('reference_number', 'like', $like)
                ->orWhere('metadata->display_tracking', $search)
                ->orWhereHas('documentCase', function (Builder $caseQuery) use ($like): void {
                    $caseQuery
                        ->where('case_number', 'like', $like)
                        ->orWhere('title', 'like', $like);
                });
        });
    }

    /**
     * Determine whether a filter value should be applied.
     */
    protected function hasFilter(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Get active department options.
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
     * Get workflow status filter options.
     *
     * @return array<int, string>
     */
    protected function workflowStatuses(): array
    {
        return array_map(
            static fn (DocumentWorkflowStatus $workflowStatus): string => $workflowStatus->value,
            DocumentWorkflowStatus::cases()
        );
    }

    /**
     * Get document type filter options.
     *
     * @return array<int, string>
     */
    protected function documentTypes(): array
    {
        return ['communication', 'submission', 'request', 'for_processing'];
    }

    /**
     * Get owner type filter options.
     *
     * @return array<int, string>
     */
    protected function ownerTypes(): array
    {
        return ['district', 'school', 'personal', 'others'];
    }

    /**
     * Build managed update payload while normalizing dependent fields.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function managedDocumentPayload(array $validated): array
    {
        $isReturnable = (bool) ($validated['is_returnable'] ?? false);

        return [
            'subject' => $validated['subject'],
            'reference_number' => $validated['reference_number'] ?? null,
            'document_type' => $validated['document_type'],
            'owner_type' => $validated['owner_type'],
            'owner_name' => $validated['owner_name'],
            'priority' => $validated['priority'],
            'due_at' => $validated['due_at'] ?? null,
            'is_returnable' => $isReturnable,
            'return_deadline' => $isReturnable ? ($validated['return_deadline'] ?? null) : null,
        ];
    }
}
