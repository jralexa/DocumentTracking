<?php

namespace App\Services;

use App\DocumentEventType;
use App\DocumentRelationshipType;
use App\Exceptions\InvalidDocumentRelationshipException;
use App\Models\Document;
use App\Models\DocumentRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentRelationshipService
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected DocumentAuditService $auditService) {}

    /**
     * Link two documents using a specific relationship type.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function link(
        Document $sourceDocument,
        Document $relatedDocument,
        DocumentRelationshipType $relationType,
        ?User $createdBy = null,
        ?string $notes = null,
        ?array $metadata = null
    ): DocumentRelationship {
        if ($sourceDocument->is($relatedDocument)) {
            $this->throwInvalidRelationship('A document cannot be related to itself.');
        }

        return DB::transaction(function () use ($sourceDocument, $relatedDocument, $relationType, $createdBy, $notes, $metadata): DocumentRelationship {
            /** @var DocumentRelationship $relationship */
            $relationship = DocumentRelationship::query()->firstOrCreate(
                [
                    'source_document_id' => $sourceDocument->id,
                    'related_document_id' => $relatedDocument->id,
                    'relation_type' => $relationType->value,
                ],
                [
                    'created_by_user_id' => $createdBy?->id,
                    'notes' => $notes,
                    'metadata' => $metadata,
                ]
            );

            if (! $relationship->wasRecentlyCreated && ($notes !== null || $metadata !== null || $createdBy !== null)) {
                $relationship->forceFill([
                    'created_by_user_id' => $createdBy?->id ?? $relationship->created_by_user_id,
                    'notes' => $notes ?? $relationship->notes,
                    'metadata' => $metadata ?? $relationship->metadata,
                ])->save();
            }

            $this->auditService->recordEvent(
                document: $sourceDocument,
                eventType: DocumentEventType::RelationshipLinked,
                actedBy: $createdBy,
                message: 'Document relationship linked.',
                context: 'relationship',
                relationship: $relationship,
                payload: [
                    'source_document_id' => $sourceDocument->id,
                    'related_document_id' => $relatedDocument->id,
                    'relation_type' => $relationType->value,
                ]
            );

            return $relationship;
        });
    }

    /**
     * Mark source documents as merged into a target document.
     *
     * @param  iterable<Document>  $sourceDocuments
     * @return array<int, DocumentRelationship>
     */
    public function mergeInto(
        Document $targetDocument,
        iterable $sourceDocuments,
        ?User $createdBy = null,
        ?string $notes = null
    ): array {
        $relationships = [];

        foreach ($sourceDocuments as $sourceDocument) {
            $relationships[] = $this->link(
                sourceDocument: $sourceDocument,
                relatedDocument: $targetDocument,
                relationType: DocumentRelationshipType::MergedInto,
                createdBy: $createdBy,
                notes: $notes
            );
        }

        return $relationships;
    }

    /**
     * Mark child documents as split from a parent document.
     *
     * @param  iterable<Document>  $childDocuments
     * @return array<int, DocumentRelationship>
     */
    public function splitFrom(
        Document $parentDocument,
        iterable $childDocuments,
        ?User $createdBy = null,
        ?string $notes = null
    ): array {
        $relationships = [];

        foreach ($childDocuments as $childDocument) {
            $relationships[] = $this->link(
                sourceDocument: $childDocument,
                relatedDocument: $parentDocument,
                relationType: DocumentRelationshipType::SplitFrom,
                createdBy: $createdBy,
                notes: $notes
            );
        }

        return $relationships;
    }

    /**
     * Attach supporting documents to a parent document.
     *
     * @param  iterable<Document>  $attachmentDocuments
     * @return array<int, DocumentRelationship>
     */
    public function attachTo(
        Document $parentDocument,
        iterable $attachmentDocuments,
        ?User $createdBy = null,
        ?string $notes = null
    ): array {
        $relationships = [];

        foreach ($attachmentDocuments as $attachmentDocument) {
            $relationships[] = $this->link(
                sourceDocument: $attachmentDocument,
                relatedDocument: $parentDocument,
                relationType: DocumentRelationshipType::AttachedTo,
                createdBy: $createdBy,
                notes: $notes
            );
        }

        return $relationships;
    }

    /**
     * Create symmetric related-to links for paired document context.
     */
    public function relate(
        Document $leftDocument,
        Document $rightDocument,
        ?User $createdBy = null,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($leftDocument, $rightDocument, $createdBy, $notes): void {
            $this->link(
                sourceDocument: $leftDocument,
                relatedDocument: $rightDocument,
                relationType: DocumentRelationshipType::RelatedTo,
                createdBy: $createdBy,
                notes: $notes
            );

            $this->link(
                sourceDocument: $rightDocument,
                relatedDocument: $leftDocument,
                relationType: DocumentRelationshipType::RelatedTo,
                createdBy: $createdBy,
                notes: $notes
            );
        });
    }

    /**
     * Throw a relationship domain exception.
     *
     * @throws InvalidDocumentRelationshipException
     */
    protected function throwInvalidRelationship(string $message): never
    {
        throw new InvalidDocumentRelationshipException($message);
    }
}
