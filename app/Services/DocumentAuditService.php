<?php

namespace App\Services;

use App\DocumentEventType;
use App\Models\Document;
use App\Models\DocumentCustody;
use App\Models\DocumentEvent;
use App\Models\DocumentItem;
use App\Models\DocumentRelationship;
use App\Models\DocumentRemark;
use App\Models\DocumentTransfer;
use App\Models\User;

class DocumentAuditService
{
    /**
     * Record an immutable event for a document.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function recordEvent(
        Document $document,
        DocumentEventType $eventType,
        ?User $actedBy = null,
        ?string $message = null,
        ?string $context = null,
        ?DocumentTransfer $transfer = null,
        ?DocumentCustody $custody = null,
        ?DocumentRelationship $relationship = null,
        ?array $payload = null
    ): DocumentEvent {
        return $document->events()->create([
            'document_transfer_id' => $transfer?->id,
            'document_custody_id' => $custody?->id,
            'document_relationship_id' => $relationship?->id,
            'acted_by_user_id' => $actedBy?->id,
            'event_type' => $eventType,
            'context' => $context ?? 'general',
            'message' => $message,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Add a remark to a document, optionally linked to transfer/item and parent remark.
     */
    public function addRemark(
        Document $document,
        string $remark,
        ?User $user = null,
        ?string $context = null,
        ?DocumentTransfer $transfer = null,
        ?DocumentItem $item = null,
        ?DocumentRemark $parentRemark = null,
        bool $isSystem = false
    ): DocumentRemark {
        $createdRemark = $document->remarks()->create([
            'document_transfer_id' => $transfer?->id,
            'document_item_id' => $item?->id,
            'parent_remark_id' => $parentRemark?->id,
            'user_id' => $user?->id,
            'context' => $context ?? 'general',
            'remark' => $remark,
            'is_system' => $isSystem,
            'remarked_at' => now(),
        ]);

        $this->recordEvent(
            document: $document,
            eventType: DocumentEventType::RemarkAdded,
            actedBy: $user,
            message: $remark,
            context: $context ?? 'general',
            transfer: $transfer,
            payload: [
                'remark_id' => $createdRemark->id,
                'is_system' => $isSystem,
                'item_id' => $item?->id,
                'parent_remark_id' => $parentRemark?->id,
            ]
        );

        return $createdRemark;
    }

}
