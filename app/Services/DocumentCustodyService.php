<?php

namespace App\Services;

use App\DocumentEventType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Exceptions\InvalidDocumentCustodyActionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentCustodyService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected DocumentAuditService $auditService) {}

    /**
     * Assign current original custody for a document.
     */
    public function assignOriginalCustody(
        Document $document,
        ?Department $department,
        ?User $custodian,
        ?string $physicalLocation = null,
        ?string $storageReference = null,
        ?string $purpose = null,
        ?string $notes = null
    ): DocumentCustody {
        if ($custodian !== null && $department !== null && $custodian->department_id !== $department->id) {
            $this->throwInvalidCustodyAction('Custodian must belong to the selected department.');
        }

        return DB::transaction(function () use ($document, $department, $custodian, $physicalLocation, $storageReference, $purpose, $notes): DocumentCustody {
            $document->custodies()
                ->where('version_type', DocumentVersionType::Original->value)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'status' => 'forwarded',
                    'released_at' => now(),
                ]);

            $custody = $document->custodies()->create([
                'department_id' => $department?->id,
                'user_id' => $custodian?->id,
                'version_type' => DocumentVersionType::Original,
                'is_current' => true,
                'status' => 'in_custody',
                'physical_location' => $physicalLocation,
                'storage_reference' => $storageReference,
                'purpose' => $purpose,
                'notes' => $notes,
                'received_at' => now(),
            ]);

            $document->forceFill([
                'original_current_department_id' => $department?->id,
                'original_custodian_user_id' => $custodian?->id,
                'original_physical_location' => $physicalLocation,
            ])->save();

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::CustodyAssigned,
                actedBy: $custodian,
                message: 'Original custody updated.',
                context: 'custody',
                custody: $custody,
                payload: [
                    'department_id' => $department?->id,
                    'storage_reference' => $storageReference,
                ]
            );

            return $custody;
        });
    }

    /**
     * Record custody for a non-original document version.
     */
    public function recordDerivativeCustody(
        Document $document,
        DocumentVersionType $versionType,
        ?Department $department,
        ?User $custodian,
        ?string $physicalLocation = null,
        ?string $storageReference = null,
        ?string $purpose = null,
        ?string $notes = null
    ): DocumentCustody {
        if ($versionType === DocumentVersionType::Original) {
            return $this->assignOriginalCustody(
                document: $document,
                department: $department,
                custodian: $custodian,
                physicalLocation: $physicalLocation,
                storageReference: $storageReference,
                purpose: $purpose,
                notes: $notes
            );
        }

        $custody = $document->custodies()->create([
            'department_id' => $department?->id,
            'user_id' => $custodian?->id,
            'version_type' => $versionType,
            'is_current' => true,
            'status' => 'in_custody',
            'physical_location' => $physicalLocation,
            'storage_reference' => $storageReference,
            'purpose' => $purpose,
            'notes' => $notes,
            'received_at' => now(),
        ]);

        $this->auditService->recordEvent(
            document: $document,
            eventType: DocumentEventType::CustodyDerivativeRecorded,
            actedBy: $custodian,
            message: 'Derivative custody record added.',
            context: 'custody',
            custody: $custody,
            payload: [
                'version_type' => $versionType->value,
                'department_id' => $department?->id,
            ]
        );

        return $custody;
    }

    /**
     * Mark a returnable document as returned and close original custody.
     */
    public function markOriginalReturned(Document $document, string $returnedTo, ?Carbon $returnedAt = null): void
    {
        if (! $document->is_returnable) {
            $this->throwInvalidCustodyAction('This document is not marked as returnable.');
        }

        if (trim($returnedTo) === '') {
            $this->throwInvalidCustodyAction('Returned-to value is required.');
        }

        if ($document->returned_at !== null) {
            $this->throwInvalidCustodyAction('This document has already been marked as returned.');
        }

        DB::transaction(function () use ($document, $returnedTo, $returnedAt): void {
            $timestamp = $returnedAt ?? now();

            $document->custodies()
                ->where('version_type', DocumentVersionType::Original->value)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'status' => 'returned',
                    'released_at' => $timestamp,
                ]);

            $document->forceFill([
                'returned_at' => $timestamp,
                'returned_to' => $returnedTo,
                'original_current_department_id' => null,
                'original_custodian_user_id' => null,
                'original_physical_location' => null,
                'status' => DocumentWorkflowStatus::Finished,
                'completed_at' => $timestamp,
                'current_department_id' => null,
                'current_user_id' => null,
            ])->save();

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::CustodyReturned,
                message: 'Original document returned to owner.',
                context: 'custody',
                payload: [
                    'returned_to' => $returnedTo,
                    'returned_at' => $timestamp->toIso8601String(),
                ]
            );
        });
    }

    /**
     * Release the original custody to another department while optionally retaining a photocopy.
     */
    public function releaseOriginalToDepartment(
        Document $document,
        User $user,
        Department $toDepartment,
        ?string $originalStorageLocation = null,
        ?string $remarks = null,
        bool $copyKept = false,
        ?string $copyStorageLocation = null,
        ?string $copyPurpose = null
    ): void {
        if ($user->department_id === null) {
            $this->throwInvalidCustodyAction('You are not assigned to a department.');
        }

        if ($document->original_current_department_id === null) {
            $this->throwInvalidCustodyAction('No active original custody is currently recorded.');
        }

        if ((int) $document->original_current_department_id !== (int) $user->department_id) {
            $this->throwInvalidCustodyAction('Only the current original holder department can release this original.');
        }

        if ((int) $toDepartment->id === (int) $document->original_current_department_id) {
            $this->throwInvalidCustodyAction('Destination department must be different from current original holder department.');
        }

        if ($copyKept && ($copyStorageLocation === null || trim($copyStorageLocation) === '')) {
            $this->throwInvalidCustodyAction('Storage location is required when keeping a copy.');
        }

        $fromDepartment = Department::query()->find($document->original_current_department_id);

        DB::transaction(function () use (
            $document,
            $user,
            $toDepartment,
            $remarks,
            $copyKept,
            $copyStorageLocation,
            $copyPurpose,
            $fromDepartment,
            $originalStorageLocation
        ): void {
            $resolvedOriginalStorageLocation = $originalStorageLocation;

            if ($resolvedOriginalStorageLocation === null || trim($resolvedOriginalStorageLocation) === '') {
                $resolvedOriginalStorageLocation = $document->original_physical_location;
            }

            $custody = $this->assignOriginalCustody(
                document: $document,
                department: $toDepartment,
                custodian: null,
                physicalLocation: $resolvedOriginalStorageLocation,
                storageReference: $resolvedOriginalStorageLocation,
                purpose: 'Released original to another department.',
                notes: $remarks
            );

            if ($copyKept) {
                $this->recordKeptCopy(
                    document: $document,
                    user: $user,
                    storageLocation: $copyStorageLocation,
                    purpose: $copyPurpose
                );

                $this->recordDerivativeCustody(
                    document: $document,
                    versionType: DocumentVersionType::Photocopy,
                    department: $fromDepartment,
                    custodian: $user,
                    physicalLocation: $copyStorageLocation,
                    storageReference: $copyStorageLocation,
                    purpose: $copyPurpose ?? 'Retained departmental photocopy while releasing original.',
                    notes: 'Copy retained during original release.'
                );
            }

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::CustodyAssigned,
                actedBy: $user,
                message: 'Original released to another department.',
                context: 'custody',
                custody: $custody,
                payload: [
                    'from_department_id' => $fromDepartment?->id,
                    'to_department_id' => $toDepartment->id,
                    'copy_kept' => $copyKept,
                    'copy_storage_location' => $copyStorageLocation,
                ]
            );

            if ($remarks !== null && $remarks !== '') {
                $this->auditService->addRemark(
                    document: $document,
                    remark: $remarks,
                    user: $user,
                    context: 'custody'
                );
            }
        });
    }

    /**
     * Record a retained photocopy entry while releasing original custody.
     */
    protected function recordKeptCopy(
        Document $document,
        User $user,
        ?string $storageLocation,
        ?string $purpose
    ): DocumentCopy {
        return $document->copies()->create([
            'document_transfer_id' => null,
            'department_id' => $user->department_id,
            'user_id' => $user->id,
            'copy_type' => DocumentVersionType::Photocopy,
            'storage_location' => $storageLocation,
            'purpose' => $purpose,
            'recorded_at' => now(),
            'is_discarded' => false,
        ]);
    }

    /**
     * Throw a custody domain exception.
     *
     * @throws InvalidDocumentCustodyActionException
     */
    protected function throwInvalidCustodyAction(string $message): never
    {
        throw new InvalidDocumentCustodyActionException($message);
    }
}
