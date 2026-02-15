<?php

namespace App\Http\Controllers;

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentListController extends Controller
{
    /**
     * Display searchable document listing.
     */
    public function index(Request $request): View
    {
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
            departmentId: $departmentId
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
     * Build document list query and return paginated records.
     */
    protected function documents(
        string $search,
        mixed $status,
        mixed $documentType,
        mixed $ownerType,
        mixed $departmentId
    ): LengthAwarePaginator {
        return Document::query()
            ->with(['documentCase', 'currentDepartment', 'currentUser', 'latestTransfer.toDepartment'])
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
}
