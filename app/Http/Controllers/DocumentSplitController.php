<?php

namespace App\Http\Controllers;

use App\DocumentRelationshipType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Http\Requests\SplitDocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\Services\DocumentCustodyService;
use App\Services\DocumentRelationshipService;
use App\TransferStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentSplitController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentCustodyService $custodyService) {}

    /**
     * Show split wizard for a parent document.
     */
    public function create(Document $document, Request $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $this->authorizeSplitAccess($document, $user);

        $activeDepartments = Department::query()
            ->where('is_active', true)
            ->where('id', '!=', $document->current_department_id)
            ->orderBy('name')
            ->get();

        $nextSuffix = $this->nextAvailableSuffix($this->existingSplitSuffixes($document));

        return view('documents.split.create', [
            'parentDocument' => $document,
            'activeDepartments' => $activeDepartments,
            'nextSuffix' => $nextSuffix,
        ]);
    }

    /**
     * Split parent document into independently routed child documents.
     */
    public function store(
        SplitDocumentRequest $request,
        Document $document,
        DocumentRelationshipService $relationshipService
    ): RedirectResponse {
        $user = $this->resolveAuthenticatedUser($request);
        $this->authorizeSplitAccess($document, $user);

        abort_if($document->current_department_id === null, 422, 'Parent document must have current department.');
        abort_if($document->document_case_id === null, 422, 'Parent document must belong to a case.');

        $validated = $request->validated();
        $childrenPayload = $validated['children'] ?? [];
        $usedSuffixes = $this->existingSplitSuffixes($document);

        $this->assertSplitCapacity($usedSuffixes, $childrenPayload);

        $createdChildren = [];
        $usedSuffixesArray = $usedSuffixes->all();
        $sourceDepartment = Department::query()->find($document->current_department_id);

        DB::transaction(function () use (
            $document,
            $user,
            $childrenPayload,
            $relationshipService,
            &$createdChildren,
            &$usedSuffixesArray,
            $sourceDepartment
        ): void {
            foreach ($childrenPayload as $childData) {
                $childRoutingPayload = $this->resolveChildRoutingPayload($document, $childData);
                $this->assertChildRoutingPayload($childRoutingPayload);

                foreach ($childData['to_department_ids'] as $toDepartmentId) {
                    $suffix = $this->reserveNextSuffix($usedSuffixesArray);
                    $toDepartment = Department::query()->findOrFail((int) $toDepartmentId);

                    $childDocument = $this->createChildDocument(
                        parentDocument: $document,
                        toDepartment: $toDepartment,
                        childData: $childData,
                        childRoutingPayload: $childRoutingPayload,
                        suffix: $suffix
                    );

                    $transfer = $this->createChildTransfer(
                        childDocument: $childDocument,
                        parentDocument: $document,
                        toDepartment: $toDepartment,
                        user: $user,
                        childData: $childData,
                        childRoutingPayload: $childRoutingPayload
                    );

                    $this->applyChildCustody(
                        childDocument: $childDocument,
                        toDepartment: $toDepartment,
                        sourceDepartment: $sourceDepartment,
                        user: $user,
                        childData: $childData,
                        childRoutingPayload: $childRoutingPayload
                    );

                    $this->recordChildCopyIfNeeded(
                        childDocument: $childDocument,
                        transfer: $transfer,
                        sourceDepartment: $sourceDepartment,
                        user: $user,
                        childRoutingPayload: $childRoutingPayload
                    );

                    $createdChildren[] = $childDocument;
                }
            }

            $relationshipService->splitFrom(
                parentDocument: $document,
                childDocuments: $createdChildren,
                createdBy: $user,
                notes: 'Parent document split into routed child documents.'
            );

            $this->markParentSplitCompleted($document, count($createdChildren));
        });

        return redirect()
            ->route('cases.show', $document->document_case_id)
            ->with('status', 'Parent document split successfully. Child documents are now routed independently.');
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
     * Ensure user can split the given parent document.
     */
    protected function authorizeSplitAccess(Document $document, User $user): void
    {
        abort_if($document->current_user_id !== $user->id, 403, 'Only current holder can split this document.');
        abort_if($document->current_department_id !== $user->department_id, 403, 'You can only split documents in your current department.');
    }

    /**
     * Ensure requested split does not exceed A-Z suffix slots.
     *
     * @param  array<int, array<string, mixed>>  $childrenPayload
     */
    protected function assertSplitCapacity(Collection $usedSuffixes, array $childrenPayload): void
    {
        $requestedDocumentsCount = collect($childrenPayload)
            ->sum(static fn (array $child): int => count($child['to_department_ids'] ?? []));

        if (($usedSuffixes->count() + $requestedDocumentsCount) > 26) {
            throw ValidationException::withMessages([
                'children' => 'Split exceeds available suffix slots (A-Z). Reduce destinations or child rows.',
            ]);
        }
    }

    /**
     * Resolve normalized child routing payload.
     *
     * @param  array<string, mixed>  $childData
     * @return array{
     *   forward_version_type:DocumentVersionType,
     *   owner_type:string,
     *   owner_name:string,
     *   copy_kept:bool,
     *   copy_storage_location:string|null,
     *   copy_purpose:string|null,
     *   original_storage_location:string|null
     * }
     */
    protected function resolveChildRoutingPayload(Document $parentDocument, array $childData): array
    {
        $forwardVersionType = isset($childData['forward_version_type'])
            ? DocumentVersionType::from((string) $childData['forward_version_type'])
            : DocumentVersionType::Original;
        $sameOwnerAsParent = (bool) ($childData['same_owner_as_parent'] ?? false);

        return [
            'forward_version_type' => $forwardVersionType,
            'owner_type' => $sameOwnerAsParent ? $parentDocument->owner_type : ($childData['owner_type'] ?? $parentDocument->owner_type),
            'owner_name' => $sameOwnerAsParent ? $parentDocument->owner_name : ($childData['owner_name'] ?? $parentDocument->owner_name),
            'copy_kept' => (bool) ($childData['copy_kept'] ?? false),
            'copy_storage_location' => $childData['copy_storage_location'] ?? null,
            'copy_purpose' => $childData['copy_purpose'] ?? null,
            'original_storage_location' => $childData['original_storage_location'] ?? null,
        ];
    }

    /**
     * Validate conditional payload requirements for child routing.
     *
     * @param  array{
     *   forward_version_type:DocumentVersionType,
     *   copy_kept:bool,
     *   copy_storage_location:string|null,
     *   original_storage_location:string|null
     * }  $childRoutingPayload
     */
    protected function assertChildRoutingPayload(array $childRoutingPayload): void
    {
        if (
            $childRoutingPayload['copy_kept']
            && ($childRoutingPayload['copy_storage_location'] === null || trim($childRoutingPayload['copy_storage_location']) === '')
        ) {
            throw ValidationException::withMessages([
                'children' => 'Storage location is required when keeping a copy.',
            ]);
        }

        if (
            $childRoutingPayload['forward_version_type'] !== DocumentVersionType::Original
            && ($childRoutingPayload['original_storage_location'] === null || trim($childRoutingPayload['original_storage_location']) === '')
        ) {
            throw ValidationException::withMessages([
                'children' => 'Original storage location is required when forwarding a non-original version.',
            ]);
        }
    }

    /**
     * Create a child document from parent split row.
     *
     * @param  array<string, mixed>  $childData
     * @param  array{
     *   owner_type:string,
     *   owner_name:string
     * }  $childRoutingPayload
     */
    protected function createChildDocument(
        Document $parentDocument,
        Department $toDepartment,
        array $childData,
        array $childRoutingPayload,
        string $suffix
    ): Document {
        $trackingNumber = $this->generateTrackingNumber(now());

        $childDocument = Document::query()->create([
            'document_case_id' => $parentDocument->document_case_id,
            'current_department_id' => $toDepartment->id,
            'current_user_id' => null,
            'tracking_number' => $trackingNumber,
            'reference_number' => null,
            'subject' => $childData['subject'],
            'document_type' => $childData['document_type'],
            'owner_type' => $childRoutingPayload['owner_type'],
            'owner_name' => $childRoutingPayload['owner_name'],
            'status' => DocumentWorkflowStatus::Outgoing,
            'priority' => $parentDocument->priority,
            'received_at' => now(),
            'metadata' => [
                'display_tracking' => $parentDocument->tracking_number.'-'.$suffix,
                'split_suffix' => $suffix,
                'parent_tracking_number' => $parentDocument->tracking_number,
                'parent_document_id' => $parentDocument->id,
            ],
            'is_returnable' => (bool) ($childData['is_returnable'] ?? false),
            'return_deadline' => $childData['return_deadline'] ?? null,
        ]);

        $childDocument->items()->create([
            'name' => $childData['subject'],
            'item_type' => 'child',
            'status' => 'active',
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        return $childDocument;
    }

    /**
     * Create initial pending transfer for a split child.
     *
     * @param  array<string, mixed>  $childData
     * @param  array{
     *   forward_version_type:DocumentVersionType,
     *   copy_kept:bool,
     *   copy_storage_location:string|null,
     *   copy_purpose:string|null
     * }  $childRoutingPayload
     */
    protected function createChildTransfer(
        Document $childDocument,
        Document $parentDocument,
        Department $toDepartment,
        User $user,
        array $childData,
        array $childRoutingPayload
    ): DocumentTransfer {
        return $childDocument->transfers()->create([
            'from_department_id' => $parentDocument->current_department_id,
            'to_department_id' => $toDepartment->id,
            'forwarded_by_user_id' => $user->id,
            'status' => TransferStatus::Pending,
            'remarks' => $childData['remarks'] ?? null,
            'forward_version_type' => $childRoutingPayload['forward_version_type'],
            'copy_kept' => $childRoutingPayload['copy_kept'],
            'copy_storage_location' => $childRoutingPayload['copy_storage_location'],
            'copy_purpose' => $childRoutingPayload['copy_purpose'],
            'forwarded_at' => now(),
        ]);
    }

    /**
     * Apply custody records based on routed version type.
     *
     * @param  array<string, mixed>  $childData
     * @param  array{
     *   forward_version_type:DocumentVersionType,
     *   original_storage_location:string|null
     * }  $childRoutingPayload
     */
    protected function applyChildCustody(
        Document $childDocument,
        Department $toDepartment,
        ?Department $sourceDepartment,
        User $user,
        array $childData,
        array $childRoutingPayload
    ): void {
        $remarks = $childData['remarks'] ?? null;

        if ($childRoutingPayload['forward_version_type'] === DocumentVersionType::Original) {
            $this->custodyService->assignOriginalCustody(
                document: $childDocument,
                department: $toDepartment,
                custodian: null,
                purpose: 'Forwarded through split routing.',
                notes: $remarks
            );

            return;
        }

        $this->custodyService->assignOriginalCustody(
            document: $childDocument,
            department: $sourceDepartment,
            custodian: $user,
            physicalLocation: $childRoutingPayload['original_storage_location'],
            storageReference: $childRoutingPayload['original_storage_location'],
            purpose: 'Original retained by source department during split routing.',
            notes: $remarks
        );

        $this->custodyService->recordDerivativeCustody(
            document: $childDocument,
            versionType: $childRoutingPayload['forward_version_type'],
            department: $toDepartment,
            custodian: null,
            purpose: 'Forwarded through split routing.',
            notes: $remarks
        );
    }

    /**
     * Record retained copy inventory and custody when enabled.
     *
     * @param  array{
     *   copy_kept:bool,
     *   copy_storage_location:string|null,
     *   copy_purpose:string|null
     * }  $childRoutingPayload
     */
    protected function recordChildCopyIfNeeded(
        Document $childDocument,
        DocumentTransfer $transfer,
        ?Department $sourceDepartment,
        User $user,
        array $childRoutingPayload
    ): void {
        if (! $childRoutingPayload['copy_kept']) {
            return;
        }

        $this->recordCopyEntry(
            document: $childDocument,
            transfer: $transfer,
            user: $user,
            storageLocation: $childRoutingPayload['copy_storage_location'],
            purpose: $childRoutingPayload['copy_purpose']
        );

        $this->custodyService->recordDerivativeCustody(
            document: $childDocument,
            versionType: DocumentVersionType::Photocopy,
            department: $sourceDepartment,
            custodian: $user,
            physicalLocation: $childRoutingPayload['copy_storage_location'],
            storageReference: $childRoutingPayload['copy_storage_location'],
            purpose: $childRoutingPayload['copy_purpose'] ?? 'Retained departmental photocopy during split routing.',
            notes: 'Copy retained during split routing.'
        );
    }

    /**
     * Mark parent metadata after split completion.
     */
    protected function markParentSplitCompleted(Document $document, int $childrenCount): void
    {
        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'split_completed' => true,
                'split_children_count' => $childrenCount,
                'split_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    /**
     * Generate a unique daily tracking number in YYMMDD### format.
     */
    protected function generateTrackingNumber(Carbon $dateTime): string
    {
        $prefix = $dateTime->format('ymd');

        $lastTrackingNumber = Document::query()
            ->where('tracking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $nextSequence = $lastTrackingNumber === null
            ? 1
            : ((int) substr($lastTrackingNumber, 6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get already used child suffixes for a parent split.
     *
     * @return Collection<int, string>
     */
    protected function existingSplitSuffixes(Document $document): Collection
    {
        $childIds = $document->incomingRelationships()
            ->where('relation_type', DocumentRelationshipType::SplitFrom->value)
            ->pluck('source_document_id');

        if ($childIds->isEmpty()) {
            return collect();
        }

        return Document::query()
            ->whereIn('id', $childIds)
            ->get(['metadata'])
            ->map(function (Document $child): ?string {
                $metadata = $child->metadata ?? [];
                $explicitSuffix = $metadata['split_suffix'] ?? null;

                if (is_string($explicitSuffix) && $explicitSuffix !== '') {
                    return strtoupper($explicitSuffix);
                }

                $displayTracking = $metadata['display_tracking'] ?? null;
                if (! is_string($displayTracking)) {
                    return null;
                }

                $parts = explode('-', $displayTracking);
                $suffix = end($parts);

                return is_string($suffix) && $suffix !== '' ? strtoupper($suffix) : null;
            })
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Determine next available suffix from A-Z.
     */
    protected function nextAvailableSuffix(Collection $usedSuffixes): string
    {
        $used = $usedSuffixes->map(static fn (string $suffix): string => strtoupper($suffix))->all();

        foreach (range('A', 'Z') as $candidate) {
            if (! in_array($candidate, $used, true)) {
                return $candidate;
            }
        }

        return 'Z';
    }

    /**
     * Reserve and return the next available suffix from the used suffix list.
     *
     * @param  array<int, string>  $usedSuffixes
     */
    protected function reserveNextSuffix(array &$usedSuffixes): string
    {
        $nextSuffix = $this->nextAvailableSuffix(collect($usedSuffixes));
        $usedSuffixes[] = $nextSuffix;

        return $nextSuffix;
    }

    /**
     * Record a copy inventory entry for retained copies.
     */
    protected function recordCopyEntry(
        Document $document,
        DocumentTransfer $transfer,
        User $user,
        ?string $storageLocation,
        ?string $purpose
    ): DocumentCopy {
        return $document->copies()->create([
            'document_transfer_id' => $transfer->id,
            'department_id' => $user->department_id,
            'user_id' => $user->id,
            'copy_type' => DocumentVersionType::Photocopy,
            'storage_location' => $storageLocation,
            'purpose' => $purpose,
            'recorded_at' => now(),
            'is_discarded' => false,
        ]);
    }
}
