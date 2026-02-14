<?php

use App\DocumentEventType;
use App\DocumentRelationshipType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentEvent;
use App\Models\DocumentItem;
use App\Models\DocumentRelationship;
use App\Models\DocumentRemark;
use App\Models\User;
use App\Services\DocumentAuditService;
use App\Services\DocumentCustodyService;
use App\Services\DocumentRelationshipService;
use App\Services\DocumentWorkflowService;

test('workflow forward records event and remark entries', function () {
    $workflowService = app(DocumentWorkflowService::class);

    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $fromDepartment->id]);

    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $workflowService->forward($document, $user, $toDepartment, 'Forward with note');

    expect(DocumentEvent::query()
        ->where('document_id', $document->id)
        ->where('event_type', DocumentEventType::WorkflowForwarded->value)
        ->exists())->toBeTrue();

    expect(DocumentRemark::query()
        ->where('document_id', $document->id)
        ->where('context', 'workflow')
        ->where('remark', 'Forward with note')
        ->exists())->toBeTrue();
});

test('audit service supports threaded remarks on document items', function () {
    $auditService = app(DocumentAuditService::class);
    $user = User::factory()->create();
    $document = Document::factory()->create();
    $item = DocumentItem::factory()->create(['document_id' => $document->id]);

    $parentRemark = $auditService->addRemark(
        document: $document,
        remark: 'Parent note',
        user: $user,
        context: 'item',
        item: $item
    );

    $childRemark = $auditService->addRemark(
        document: $document,
        remark: 'Child reply',
        user: $user,
        context: 'item',
        item: $item,
        parentRemark: $parentRemark
    );

    expect($childRemark->parentRemark?->is($parentRemark))->toBeTrue();
    expect($parentRemark->childRemarks()->whereKey($childRemark->id)->exists())->toBeTrue();
    expect(DocumentEvent::query()
        ->where('document_id', $document->id)
        ->where('event_type', DocumentEventType::RemarkAdded->value)
        ->count())->toBe(2);
});

test('custody service actions record custody events', function () {
    $custodyService = app(DocumentCustodyService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'is_returnable' => true,
    ]);

    $custodyService->assignOriginalCustody(
        document: $document,
        department: $department,
        custodian: $user,
        physicalLocation: 'Cabinet C'
    );

    $custodyService->recordDerivativeCustody(
        document: $document,
        versionType: DocumentVersionType::Photocopy,
        department: $department,
        custodian: $user
    );

    $custodyService->markOriginalReturned($document, 'Owner X');

    expect(DocumentEvent::query()->where('document_id', $document->id)->where('event_type', DocumentEventType::CustodyAssigned->value)->exists())->toBeTrue();
    expect(DocumentEvent::query()->where('document_id', $document->id)->where('event_type', DocumentEventType::CustodyDerivativeRecorded->value)->exists())->toBeTrue();
    expect(DocumentEvent::query()->where('document_id', $document->id)->where('event_type', DocumentEventType::CustodyReturned->value)->exists())->toBeTrue();
});

test('relationship service records relationship linked event', function () {
    $relationshipService = app(DocumentRelationshipService::class);
    $actor = User::factory()->create();
    $source = Document::factory()->create();
    $target = Document::factory()->create();

    $relationshipService->link($source, $target, DocumentRelationshipType::AttachedTo, $actor, 'Attached for processing');

    $relationship = DocumentRelationship::query()->where('source_document_id', $source->id)->firstOrFail();

    expect(DocumentEvent::query()
        ->where('document_id', $source->id)
        ->where('document_relationship_id', $relationship->id)
        ->where('event_type', DocumentEventType::RelationshipLinked->value)
        ->exists())->toBeTrue();
});

test('document models expose event and remark relationships', function () {
    $document = Document::factory()->create();
    DocumentEvent::factory()->create(['document_id' => $document->id]);
    DocumentRemark::factory()->create(['document_id' => $document->id]);

    expect($document->events()->count())->toBe(1);
    expect($document->remarks()->count())->toBe(1);
});
