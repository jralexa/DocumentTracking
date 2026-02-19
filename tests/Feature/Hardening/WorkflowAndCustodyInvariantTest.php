<?php

use App\DocumentWorkflowStatus;
use App\Exceptions\InvalidDocumentCustodyActionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\Services\DocumentCustodyService;
use App\TransferStatus;

test('cannot accept document that is not outgoing', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $receiver = User::factory()->create(['department_id' => $toDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($receiver)->post(route('documents.accept', $document));

    $response->assertRedirect();
    $response->assertSessionHasErrors('workflow');
});

test('cannot forward document when user department differs from document current department', function () {
    $documentDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $destinationDepartment = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $otherDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $documentDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($user)->post(route('documents.forward', $document), [
        'to_department_id' => $destinationDepartment->id,
    ]);

    $response->assertForbidden();
});

test('cannot recall when document is not outgoing', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => $forwarder->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $transfer = DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($forwarder)->post(route('documents.recall', $transfer));

    $response->assertRedirect();
    $response->assertSessionHasErrors('workflow');
});

test('cannot assign original custody when custodian does not belong to target department', function () {
    $service = app(DocumentCustodyService::class);
    $document = Document::factory()->create();
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $custodian = User::factory()->create(['department_id' => $departmentA->id]);

    $this->expectException(InvalidDocumentCustodyActionException::class);

    $service->assignOriginalCustody($document, $departmentB, $custodian);
});

test('cannot mark non returnable document as returned', function () {
    $service = app(DocumentCustodyService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create(['is_returnable' => false]);

    $this->expectException(InvalidDocumentCustodyActionException::class);

    $service->markOriginalReturned($document, $user, 'Owner Name');
});

test('cannot mark document returned twice', function () {
    $service = app(DocumentCustodyService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'is_returnable' => true,
        'returned_at' => now()->subDay(),
        'returned_to' => 'Owner Name',
        'original_current_department_id' => $department->id,
    ]);

    $this->expectException(InvalidDocumentCustodyActionException::class);

    $service->markOriginalReturned($document, $user, 'Owner Name');
});
