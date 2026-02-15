<?php

namespace App\Http\Controllers;

use App\DocumentVersionType;
use App\Exceptions\InvalidDocumentCustodyActionException;
use App\Http\Requests\MarkReturnableDocumentRequest;
use App\Http\Requests\ReleaseOriginalCustodyRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Services\DocumentCustodyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentCustodyController extends Controller
{
    /**
     * Display current original custody records.
     */
    public function originals(Request $request): View
    {
        $departmentId = $request->query('department_id');
        $search = trim((string) $request->query('q', ''));

        $records = DocumentCustody::query()
            ->current()
            ->original()
            ->with(['document.documentCase', 'department', 'user'])
            ->when($this->hasFilter($departmentId), fn (Builder $query) => $query->where('department_id', (int) $departmentId))
            ->when($search !== '', fn (Builder $query) => $this->applyDocumentSearchFilter($query, $search))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $activeDepartments = $this->activeDepartments();

        return view('custody.originals', [
            'records' => $records,
            'activeDepartments' => $activeDepartments,
            'filters' => [
                'q' => $search,
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Display copy inventory records.
     */
    public function copies(Request $request): View
    {
        $departmentId = $request->query('department_id');
        $copyType = $request->query('copy_type');
        $search = trim((string) $request->query('q', ''));

        $records = DocumentCopy::query()
            ->where('is_discarded', false)
            ->with(['document.documentCase', 'department', 'user', 'transfer.toDepartment'])
            ->when($this->hasFilter($departmentId), fn (Builder $query) => $query->where('department_id', (int) $departmentId))
            ->when($this->hasFilter($copyType), fn (Builder $query) => $query->where('copy_type', $copyType))
            ->when($search !== '', fn (Builder $query) => $this->applyDocumentSearchFilter($query, $search))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $activeDepartments = $this->activeDepartments();

        return view('custody.copies', [
            'records' => $records,
            'activeDepartments' => $activeDepartments,
            'copyTypes' => array_map(static fn (DocumentVersionType $versionType): string => $versionType->value, DocumentVersionType::cases()),
            'filters' => [
                'q' => $search,
                'department_id' => $departmentId,
                'copy_type' => $copyType,
            ],
        ]);
    }

    /**
     * Display returnable documents with due/overdue tracking.
     */
    public function returnables(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $state = $request->query('state', 'pending');

        $documents = $this->returnableDocuments($state, $search);

        return view('custody.returnables', [
            'documents' => $documents,
            'filters' => [
                'q' => $search,
                'state' => $state,
            ],
        ]);
    }

    /**
     * Mark a returnable document original as returned to owner.
     */
    public function markReturned(
        MarkReturnableDocumentRequest $request,
        Document $document,
        DocumentCustodyService $custodyService
    ): RedirectResponse {
        try {
            $custodyService->markOriginalReturned(
                document: $document,
                returnedTo: $request->validated('returned_to'),
                returnedAt: $this->resolveReturnedAt($request)
            );
        } catch (InvalidDocumentCustodyActionException $exception) {
            throw ValidationException::withMessages([
                'returnable' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Returnable original has been marked as returned.');
    }

    /**
     * Release original custody to another department.
     */
    public function releaseOriginal(
        ReleaseOriginalCustodyRequest $request,
        Document $document,
        DocumentCustodyService $custodyService
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $toDepartment = Department::query()->findOrFail((int) $request->validated('to_department_id'));
        $originalStorageLocation = $request->validated('original_storage_location');
        $remarks = $request->validated('remarks');
        $copyKept = (bool) ($request->validated('copy_kept') ?? false);
        $copyStorageLocation = $request->validated('copy_storage_location');
        $copyPurpose = $request->validated('copy_purpose');

        try {
            $custodyService->releaseOriginalToDepartment(
                document: $document,
                user: $user,
                toDepartment: $toDepartment,
                originalStorageLocation: $originalStorageLocation,
                remarks: $remarks,
                copyKept: $copyKept,
                copyStorageLocation: $copyStorageLocation,
                copyPurpose: $copyPurpose
            );
        } catch (InvalidDocumentCustodyActionException $exception) {
            throw ValidationException::withMessages([
                'release_original' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Original released to destination department successfully.');
    }

    /**
     * Get active departments used by custody filters.
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
     * Determine whether a query filter value should be applied.
     */
    protected function hasFilter(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Apply document search filter to a parent query via `whereHas`.
     */
    protected function applyDocumentSearchFilter(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->whereHas('document', function (Builder $documentQuery) use ($search, $like): void {
            $documentQuery
                ->where('tracking_number', $search)
                ->orWhere('subject', 'like', $like)
                ->orWhere('owner_name', 'like', $like)
                ->orWhere('metadata->display_tracking', $search);
        });
    }

    /**
     * Resolve returnable document listing.
     */
    protected function returnableDocuments(string $state, string $search): LengthAwarePaginator
    {
        return Document::query()
            ->with(['documentCase', 'currentDepartment', 'originalCurrentDepartment', 'originalCustodian'])
            ->where('is_returnable', true)
            ->when($state === 'pending', fn (Builder $query) => $query->whereNull('returned_at'))
            ->when($state === 'returned', fn (Builder $query) => $query->whereNotNull('returned_at'))
            ->when(
                $state === 'overdue',
                fn (Builder $query) => $query->whereNull('returned_at')->whereDate('return_deadline', '<', now()->toDateString())
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($search, $like): void {
                    $searchQuery
                        ->where('tracking_number', $search)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('owner_name', 'like', $like)
                        ->orWhere('metadata->display_tracking', $search);
                });
            })
            ->orderByRaw('return_deadline IS NULL')
            ->orderBy('return_deadline')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Resolve optional returned-at timestamp.
     */
    protected function resolveReturnedAt(MarkReturnableDocumentRequest $request): ?Carbon
    {
        $returnedAt = $request->validated('returned_at');

        if ($returnedAt === null) {
            return null;
        }

        return Carbon::parse($returnedAt);
    }
}
