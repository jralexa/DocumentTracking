<?php

namespace App\Services;

use App\DocumentEventType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Exceptions\UnauthorizedWorkflowActionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected DocumentAuditService $auditService,
        protected DocumentCustodyService $custodyService
    ) {}

    /**
     * Accept an incoming document transfer assigned to the user's department.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    public function accept(Document $document, User $user): void
    {
        $this->assertUserHasDepartment($user);
        $this->assertDocumentOutgoingForAcceptance($document);
        $latestTransfer = $this->resolvePendingLatestTransferForAcceptance($document);
        $this->assertTransferRoutedToUserDepartment($latestTransfer, $user);

        DB::transaction(function () use ($document, $latestTransfer, $user): void {
            $latestTransfer->forceFill([
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
                'status' => TransferStatus::Accepted,
            ])->save();

            $document->forceFill([
                'current_department_id' => $user->department_id,
                'current_user_id' => $user->id,
                'status' => DocumentWorkflowStatus::OnQueue,
                'received_at' => now(),
            ])->save();

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::WorkflowAccepted,
                actedBy: $user,
                message: 'Document accepted into personal queue.',
                context: 'workflow',
                transfer: $latestTransfer
            );

        });

    }

    /**
     * Forward a document to another active department.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    public function forward(
        Document $document,
        User $user,
        Department $toDepartment,
        ?string $remarks,
        ?DocumentVersionType $forwardVersionType = null,
        bool $copyKept = false,
        ?string $copyStorageLocation = null,
        ?string $copyPurpose = null
    ): void {
        $resolvedForwardVersionType = $forwardVersionType ?? DocumentVersionType::Original;

        $this->assertForwardingAllowed($document, $user, $toDepartment);
        $this->assertCopyRetentionInput($copyKept, $copyStorageLocation);

        DB::transaction(function () use (
            $document,
            $user,
            $toDepartment,
            $remarks,
            $resolvedForwardVersionType,
            $copyKept,
            $copyStorageLocation,
            $copyPurpose
        ): void {
            $transfer = $document->transfers()->create([
                'from_department_id' => $document->current_department_id,
                'to_department_id' => $toDepartment->id,
                'forwarded_by_user_id' => $user->id,
                'status' => TransferStatus::Pending,
                'remarks' => $remarks,
                'forward_version_type' => $resolvedForwardVersionType,
                'copy_kept' => $copyKept,
                'copy_storage_location' => $copyStorageLocation,
                'copy_purpose' => $copyPurpose,
                'forwarded_at' => now(),
            ]);

            if ($resolvedForwardVersionType === DocumentVersionType::Original) {
                $destinationCustody = $this->custodyService->assignOriginalCustody(
                    document: $document,
                    department: $toDepartment,
                    custodian: null,
                    purpose: 'Forwarded through workflow transfer.',
                    notes: $remarks
                );
            } else {
                $destinationCustody = $this->custodyService->recordDerivativeCustody(
                    document: $document,
                    versionType: $resolvedForwardVersionType,
                    department: $toDepartment,
                    custodian: null,
                    purpose: 'Forwarded through workflow transfer.',
                    notes: $remarks
                );
            }

            if ($copyKept) {
                $this->recordCopyEntry(
                    document: $document,
                    transfer: $transfer,
                    user: $user,
                    storageLocation: $copyStorageLocation,
                    purpose: $copyPurpose
                );

                $this->custodyService->recordDerivativeCustody(
                    document: $document,
                    versionType: DocumentVersionType::Photocopy,
                    department: $user->department,
                    custodian: $user,
                    physicalLocation: $copyStorageLocation,
                    storageReference: $copyStorageLocation,
                    purpose: $copyPurpose ?? 'Retained departmental photocopy.',
                    notes: 'Copy retained during forwarding.'
                );
            }

            $document->forceFill([
                'current_department_id' => $toDepartment->id,
                'current_user_id' => null,
                'status' => DocumentWorkflowStatus::Outgoing,
            ])->save();

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::WorkflowForwarded,
                actedBy: $user,
                message: 'Document forwarded to a department queue.',
                context: 'workflow',
                transfer: $transfer,
                custody: $destinationCustody,
                payload: [
                    'from_department_id' => $transfer->from_department_id,
                    'to_department_id' => $transfer->to_department_id,
                    'forward_version_type' => $resolvedForwardVersionType->value,
                    'copy_kept' => $copyKept,
                    'copy_storage_location' => $copyStorageLocation,
                ]
            );

            if ($remarks !== null && $remarks !== '') {
                $this->auditService->addRemark(
                    document: $document,
                    remark: $remarks,
                    user: $user,
                    context: 'workflow',
                    transfer: $transfer
                );
            }

        });

    }

    /**
     * Recall a pending outgoing transfer before the destination accepts.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    public function recall(DocumentTransfer $transfer, User $user): void
    {
        $document = $this->resolveDocumentForTransfer($transfer);
        $this->assertTransferIsLatestForRecall($document, $transfer);
        $this->assertDocumentOutgoingForRecall($document);
        $this->assertTransferPendingForRecall($transfer);
        $this->assertRecallAuthorized($transfer, $user);

        DB::transaction(function () use ($transfer, $document, $user): void {
            $transfer->forceFill([
                'status' => TransferStatus::Recalled,
                'recalled_at' => now(),
                'recalled_by_user_id' => $user->id,
            ])->save();

            $document->forceFill([
                'current_department_id' => $transfer->from_department_id,
                'current_user_id' => $user->id,
                'status' => DocumentWorkflowStatus::OnQueue,
            ])->save();

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::WorkflowRecalled,
                actedBy: $user,
                message: 'Outgoing transfer recalled before acceptance.',
                context: 'workflow',
                transfer: $transfer
            );

        });

    }

    /**
     * Get the latest transfer for a document.
     */
    protected function getLatestTransfer(Document $document): ?DocumentTransfer
    {
        return $document->transfers()->latest('id')->first();
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

    /**
     * Assert that the acting user is assigned to a department.
     *
     * @throws UnauthorizedWorkflowActionException
     */
    protected function assertUserHasDepartment(User $user): void
    {
        if ($user->department_id === null) {
            $this->throwUnauthorizedWorkflowAction('You are not assigned to a department.');
        }
    }

    /**
     * Assert document state allows acceptance.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function assertDocumentOutgoingForAcceptance(Document $document): void
    {
        if ($document->status !== DocumentWorkflowStatus::Outgoing) {
            $this->throwInvalidWorkflowTransition('Only outgoing documents can be accepted.');
        }
    }

    /**
     * Resolve latest transfer and ensure it remains pending for acceptance.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function resolvePendingLatestTransferForAcceptance(Document $document): DocumentTransfer
    {
        $latestTransfer = $this->getLatestTransfer($document);

        if ($latestTransfer === null) {
            $this->throwInvalidWorkflowTransition('No transfer record was found for this document.');
        }

        if ($latestTransfer->status !== TransferStatus::Pending || $latestTransfer->accepted_at !== null) {
            $this->throwInvalidWorkflowTransition('This document is no longer pending acceptance.');
        }

        return $latestTransfer;
    }

    /**
     * Assert transfer destination matches acting user's department.
     *
     * @throws UnauthorizedWorkflowActionException
     */
    protected function assertTransferRoutedToUserDepartment(DocumentTransfer $transfer, User $user): void
    {
        if ($transfer->to_department_id !== $user->department_id) {
            $this->throwUnauthorizedWorkflowAction('You can only accept documents routed to your department.');
        }
    }

    /**
     * Assert forwarding preconditions and authorization.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    protected function assertForwardingAllowed(Document $document, User $user, Department $toDepartment): void
    {
        if (! $toDepartment->is_active) {
            $this->throwInvalidWorkflowTransition('Selected destination department is inactive.');
        }

        if ($document->status !== DocumentWorkflowStatus::OnQueue) {
            $this->throwInvalidWorkflowTransition('Only queued documents can be forwarded.');
        }

        if ($document->current_user_id !== $user->id) {
            $this->throwUnauthorizedWorkflowAction('Only the current assignee can forward this document.');
        }

        if ($document->current_department_id === null) {
            $this->throwInvalidWorkflowTransition('Document must have a current department before forwarding.');
        }

        if ($user->department_id !== $document->current_department_id) {
            $this->throwUnauthorizedWorkflowAction('You can only forward documents from your current department.');
        }

        if ($document->current_department_id === $toDepartment->id) {
            $this->throwInvalidWorkflowTransition('Cannot forward a document to the same department.');
        }
    }

    /**
     * Assert copy retention payload is complete when required.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function assertCopyRetentionInput(bool $copyKept, ?string $copyStorageLocation): void
    {
        if ($copyKept && ($copyStorageLocation === null || trim($copyStorageLocation) === '')) {
            $this->throwInvalidWorkflowTransition('Storage location is required when keeping a copy.');
        }
    }

    /**
     * Resolve the document tied to a transfer.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function resolveDocumentForTransfer(DocumentTransfer $transfer): Document
    {
        $transfer->loadMissing('document');
        $document = $transfer->document;

        if ($document === null) {
            $this->throwInvalidWorkflowTransition('Transfer does not have an associated document.');
        }

        return $document;
    }

    /**
     * Assert transfer is the latest transfer of the document.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function assertTransferIsLatestForRecall(Document $document, DocumentTransfer $transfer): void
    {
        $latestTransfer = $this->getLatestTransfer($document);

        if ($latestTransfer === null || $latestTransfer->id !== $transfer->id) {
            $this->throwInvalidWorkflowTransition('Only the latest transfer can be recalled.');
        }
    }

    /**
     * Assert document state allows transfer recall.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function assertDocumentOutgoingForRecall(Document $document): void
    {
        if ($document->status !== DocumentWorkflowStatus::Outgoing) {
            $this->throwInvalidWorkflowTransition('Only outgoing documents can be recalled.');
        }
    }

    /**
     * Assert transfer remains pending and unaccepted for recall.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function assertTransferPendingForRecall(DocumentTransfer $transfer): void
    {
        if ($transfer->status !== TransferStatus::Pending || $transfer->accepted_at !== null) {
            $this->throwInvalidWorkflowTransition('Only unaccepted pending transfers can be recalled.');
        }
    }

    /**
     * Assert recall is authorized for the acting user.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    protected function assertRecallAuthorized(DocumentTransfer $transfer, User $user): void
    {
        if ($transfer->from_department_id === null) {
            $this->throwInvalidWorkflowTransition('Cannot recall a transfer without a source department.');
        }

        if ($transfer->forwarded_by_user_id !== $user->id) {
            $this->throwUnauthorizedWorkflowAction('Only the forwarding user can recall this transfer.');
        }

        if ($user->department_id !== $transfer->from_department_id) {
            $this->throwUnauthorizedWorkflowAction('You can only recall transfers from your current department.');
        }
    }

    /**
     * Throw a workflow transition domain exception.
     *
     * @throws InvalidWorkflowTransitionException
     */
    protected function throwInvalidWorkflowTransition(string $message): never
    {
        throw new InvalidWorkflowTransitionException($message);
    }

    /**
     * Throw an unauthorized workflow action domain exception.
     *
     * @throws UnauthorizedWorkflowActionException
     */
    protected function throwUnauthorizedWorkflowAction(string $message): never
    {
        throw new UnauthorizedWorkflowActionException($message);
    }
}
