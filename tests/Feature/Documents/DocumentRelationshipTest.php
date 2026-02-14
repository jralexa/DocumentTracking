<?php

use App\DocumentRelationshipType;
use App\Exceptions\InvalidDocumentRelationshipException;
use App\Models\Document;
use App\Models\DocumentRelationship;
use App\Models\User;
use App\Services\DocumentRelationshipService;

test('merge creates source to target merged into relationships', function () {
    $service = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $target = Document::factory()->create();
    $sourceA = Document::factory()->create();
    $sourceB = Document::factory()->create();

    $relationships = $service->mergeInto($target, [$sourceA, $sourceB], $actor, 'Merged as one case');

    expect($relationships)->toHaveCount(2);
    expect(DocumentRelationship::query()->where('relation_type', DocumentRelationshipType::MergedInto->value)->count())->toBe(2);
    expect(DocumentRelationship::query()->where('source_document_id', $sourceA->id)->where('related_document_id', $target->id)->exists())->toBeTrue();
    expect(DocumentRelationship::query()->where('source_document_id', $sourceB->id)->where('related_document_id', $target->id)->exists())->toBeTrue();
});

test('split creates child to parent split from relationships', function () {
    $service = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $parent = Document::factory()->create();
    $childA = Document::factory()->create();
    $childB = Document::factory()->create();

    $relationships = $service->splitFrom($parent, [$childA, $childB], $actor, 'Split for processing');

    expect($relationships)->toHaveCount(2);
    expect(DocumentRelationship::query()->where('relation_type', DocumentRelationshipType::SplitFrom->value)->count())->toBe(2);
    expect(DocumentRelationship::query()->where('source_document_id', $childA->id)->where('related_document_id', $parent->id)->exists())->toBeTrue();
    expect(DocumentRelationship::query()->where('source_document_id', $childB->id)->where('related_document_id', $parent->id)->exists())->toBeTrue();
});

test('attach creates attached to relationships', function () {
    $service = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $parent = Document::factory()->create();
    $attachment = Document::factory()->create();

    $service->attachTo($parent, [$attachment], $actor, 'Supporting attachment');

    expect(DocumentRelationship::query()
        ->where('source_document_id', $attachment->id)
        ->where('related_document_id', $parent->id)
        ->where('relation_type', DocumentRelationshipType::AttachedTo->value)
        ->exists())->toBeTrue();
});

test('relate creates symmetric related to links', function () {
    $service = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $left = Document::factory()->create();
    $right = Document::factory()->create();

    $service->relate($left, $right, $actor, 'Contextually related');

    expect(DocumentRelationship::query()
        ->where('source_document_id', $left->id)
        ->where('related_document_id', $right->id)
        ->where('relation_type', DocumentRelationshipType::RelatedTo->value)
        ->exists())->toBeTrue();

    expect(DocumentRelationship::query()
        ->where('source_document_id', $right->id)
        ->where('related_document_id', $left->id)
        ->where('relation_type', DocumentRelationshipType::RelatedTo->value)
        ->exists())->toBeTrue();
});

test('link prevents self relationships', function () {
    $service = app(DocumentRelationshipService::class);
    $document = Document::factory()->create();

    $this->expectException(InvalidDocumentRelationshipException::class);

    $service->link($document, $document, DocumentRelationshipType::RelatedTo);
});

test('duplicate link is upserted and not duplicated', function () {
    $service = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $source = Document::factory()->create();
    $related = Document::factory()->create();

    $service->link(
        sourceDocument: $source,
        relatedDocument: $related,
        relationType: DocumentRelationshipType::MergedInto,
        createdBy: $actor,
        notes: 'First note'
    );

    $service->link(
        sourceDocument: $source,
        relatedDocument: $related,
        relationType: DocumentRelationshipType::MergedInto,
        createdBy: $actor,
        notes: 'Updated note',
        metadata: ['batch' => 'M-001']
    );

    $relationship = DocumentRelationship::query()
        ->where('source_document_id', $source->id)
        ->where('related_document_id', $related->id)
        ->where('relation_type', DocumentRelationshipType::MergedInto->value)
        ->firstOrFail();

    expect(DocumentRelationship::query()->count())->toBe(1);
    expect($relationship->notes)->toBe('Updated note');
    expect($relationship->metadata)->toBe(['batch' => 'M-001']);
});

test('document outgoing and incoming relationship helpers resolve records', function () {
    $service = app(DocumentRelationshipService::class);
    $source = Document::factory()->create();
    $target = Document::factory()->create();

    $service->link($source, $target, DocumentRelationshipType::MergedInto);

    $source->refresh();
    $target->refresh();

    expect($source->outgoingRelationships()->count())->toBe(1);
    expect($target->incomingRelationships()->count())->toBe(1);
    expect($source->mergedIntoRelationships()->count())->toBe(1);
    expect($source->splitFromRelationships()->count())->toBe(0);
});
