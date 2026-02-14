<?php

namespace App\Services;

use App\DocumentEventType;
use App\DocumentVersionType;
use App\Exceptions\InvalidDocumentCustodyActionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCustody;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentCustodyService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected DocumentAuditService $auditService)
    {
    }

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
            throw new InvalidDocumentCustodyActionException('Custodian must belong to the selected department.');
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
            throw new InvalidDocumentCustodyActionException('This document is not marked as returnable.');
        }

        if (trim($returnedTo) === '') {
            throw new InvalidDocumentCustodyActionException('Returned-to value is required.');
        }

        if ($document->returned_at !== null) {
            throw new InvalidDocumentCustodyActionException('This document has already been marked as returned.');
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
}
