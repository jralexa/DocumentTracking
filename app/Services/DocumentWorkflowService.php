<?php

namespace App\Services;

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Exceptions\UnauthorizedWorkflowActionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected DocumentAuditService $auditService)
    {
    }

    /**
     * Accept an incoming document transfer assigned to the user's department.
     *
     * @throws InvalidWorkflowTransitionException
     * @throws UnauthorizedWorkflowActionException
     */
    public function accept(Document $document, User $user): void
    {
        if ($user->department_id === null) {
            throw new UnauthorizedWorkflowActionException('You are not assigned to a department.');
        }

        if ($document->status !== DocumentWorkflowStatus::Outgoing) {
            throw new InvalidWorkflowTransitionException('Only outgoing documents can be accepted.');
        }

        $latestTransfer = $this->getLatestTransfer($document);

        if ($latestTransfer === null) {
            throw new InvalidWorkflowTransitionException('No transfer record was found for this document.');
        }

        if ($latestTransfer->status !== TransferStatus::Pending || $latestTransfer->accepted_at !== null) {
            throw new InvalidWorkflowTransitionException('This document is no longer pending acceptance.');
        }

        if ($latestTransfer->to_department_id !== $user->department_id) {
            throw new UnauthorizedWorkflowActionException('You can only accept documents routed to your department.');
        }

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
    public function forward(Document $document, User $user, Department $toDepartment, ?string $remarks): void
    {
        if (! $toDepartment->is_active) {
            throw new InvalidWorkflowTransitionException('Selected destination department is inactive.');
        }

        if ($document->status !== DocumentWorkflowStatus::OnQueue) {
            throw new InvalidWorkflowTransitionException('Only queued documents can be forwarded.');
        }

        if ($document->current_user_id !== $user->id) {
            throw new UnauthorizedWorkflowActionException('Only the current assignee can forward this document.');
        }

        if ($document->current_department_id === null) {
            throw new InvalidWorkflowTransitionException('Document must have a current department before forwarding.');
        }

        if ($user->department_id !== $document->current_department_id) {
            throw new UnauthorizedWorkflowActionException('You can only forward documents from your current department.');
        }

        if ($document->current_department_id === $toDepartment->id) {
            throw new InvalidWorkflowTransitionException('Cannot forward a document to the same department.');
        }

        DB::transaction(function () use ($document, $user, $toDepartment, $remarks): void {
            $transfer = $document->transfers()->create([
                'from_department_id' => $document->current_department_id,
                'to_department_id' => $toDepartment->id,
                'forwarded_by_user_id' => $user->id,
                'status' => TransferStatus::Pending,
                'remarks' => $remarks,
                'forwarded_at' => now(),
            ]);

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
                payload: [
                    'from_department_id' => $transfer->from_department_id,
                    'to_department_id' => $transfer->to_department_id,
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
        $transfer->loadMissing('document');
        $document = $transfer->document;

        if ($document === null) {
            throw new InvalidWorkflowTransitionException('Transfer does not have an associated document.');
        }

        $latestTransfer = $this->getLatestTransfer($document);

        if ($latestTransfer === null || $latestTransfer->id !== $transfer->id) {
            throw new InvalidWorkflowTransitionException('Only the latest transfer can be recalled.');
        }

        if ($document->status !== DocumentWorkflowStatus::Outgoing) {
            throw new InvalidWorkflowTransitionException('Only outgoing documents can be recalled.');
        }

        if ($transfer->status !== TransferStatus::Pending || $transfer->accepted_at !== null) {
            throw new InvalidWorkflowTransitionException('Only unaccepted pending transfers can be recalled.');
        }

        if ($transfer->from_department_id === null) {
            throw new InvalidWorkflowTransitionException('Cannot recall a transfer without a source department.');
        }

        if ($transfer->forwarded_by_user_id !== $user->id) {
            throw new UnauthorizedWorkflowActionException('Only the forwarding user can recall this transfer.');
        }

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
}
