<?php

use App\DocumentEventType;
use App\DocumentRelationshipType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\DocumentRelationship;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;

function createBaseDocument(array $overrides = []): Document
{
    return Document::factory()->create(array_merge([
        'document_case_id' => DocumentCase::factory()->create()->id,
        'status' => DocumentWorkflowStatus::Outgoing,
        'current_user_id' => null,
    ], $overrides));
}

test('accepts incoming document when transfer targets user department', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id, 'role' => UserRole::Regular]);
    $receiver = User::factory()->create(['department_id' => $toDepartment->id, 'role' => UserRole::Regular]);
    $document = createBaseDocument(['current_department_id' => $toDepartment->id]);

    $transfer = DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($receiver)->post(route('documents.accept', $document));

    $response->assertRedirect();
    $document->refresh();
    $transfer->refresh();

    expect($transfer->status)->toBe(TransferStatus::Accepted);
    expect($transfer->accepted_by_user_id)->toBe($receiver->id);
    expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($document->current_user_id)->toBe($receiver->id);
});

test('cannot accept document not targeted to user department', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $receiver = User::factory()->create(['department_id' => $otherDepartment->id]);
    $document = createBaseDocument(['current_department_id' => $toDepartment->id]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($receiver)->post(route('documents.accept', $document));

    $response->assertForbidden();
});

test('forwards document only when user is current assignee', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create(['is_active' => true]);
    $assignee = User::factory()->create(['department_id' => $fromDepartment->id]);
    $otherUser = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $assignee->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($otherUser)->post(route('documents.forward', $document), [
        'to_department_id' => $toDepartment->id,
    ]);

    $response->assertForbidden();
    expect(DocumentTransfer::query()->where('document_id', $document->id)->count())->toBe(0);
});

test('cannot forward to same department', function () {
    $department = Department::factory()->create();
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $assignee->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($assignee)->post(route('documents.forward', $document), [
        'to_department_id' => $department->id,
    ]);

    $response->assertSessionHasErrors('to_department_id');
});

test('marks queued document as finished and clears personal assignee', function () {
    $department = Department::factory()->create();
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $assignee->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'completed_at' => null,
    ]);

    $response = $this->actingAs($assignee)->post(route('documents.complete', $document), [
        'remarks' => 'Settled and no further routing needed.',
    ]);

    $response->assertRedirect();
    $document->refresh();

    expect($document->status)->toBe(DocumentWorkflowStatus::Finished);
    expect($document->completed_at)->not->toBeNull();
    expect($document->current_user_id)->toBeNull();
    expect($document->current_department_id)->toBe($department->id);
    expect(
        $document->events()
            ->where('event_type', DocumentEventType::WorkflowCompleted->value)
            ->exists()
    )->toBeTrue();
});

test('cannot finish document when actor is not current assignee', function () {
    $department = Department::factory()->create();
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $otherUser = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $assignee->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($otherUser)->post(route('documents.complete', $document));

    $response->assertForbidden();
    $document->refresh();
    expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
});

test('cannot finish document that is not currently on queue', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    $response = $this->actingAs($user)->post(route('documents.complete', $document));

    $response->assertRedirect();
    $response->assertSessionHasErrors('workflow');
    $document->refresh();
    expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
});

test('recalls pending outgoing before destination accepts', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
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
    $transfer->refresh();
    $document->refresh();

    expect($transfer->status)->toBe(TransferStatus::Recalled);
    expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($document->current_user_id)->toBe($forwarder->id);
});

test('cannot recall after destination accepted', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $receiver = User::factory()->create(['department_id' => $toDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => $receiver->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $transfer = DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'accepted_by_user_id' => $receiver->id,
        'status' => TransferStatus::Accepted,
        'accepted_at' => now(),
    ]);

    $response = $this->actingAs($forwarder)->post(route('documents.recall', $transfer));

    $response->assertRedirect();
    $response->assertSessionHasErrors('workflow');
});

test('cannot recall transfer not forwarded by actor', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $otherUser = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    $transfer = DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($otherUser)->post(route('documents.recall', $transfer));

    $response->assertForbidden();
});

test('cannot recall transfer when user is no longer in source department', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $newDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    $transfer = DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $forwarder->update(['department_id' => $newDepartment->id]);

    $response = $this->actingAs($forwarder)->post(route('documents.recall', $transfer));

    $response->assertForbidden();
});

test('queue index shows correct incoming on queue and outgoing segmentation', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $departmentA->id, 'role' => UserRole::Regular]);

    $incomingDocument = Document::factory()->create([
        'current_department_id' => $departmentA->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);
    DocumentTransfer::factory()->create([
        'document_id' => $incomingDocument->id,
        'to_department_id' => $departmentA->id,
        'forwarded_by_user_id' => User::factory()->create(['department_id' => $departmentB->id])->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $onQueueDocument = Document::factory()->create([
        'current_department_id' => $departmentA->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $outgoingDocument = Document::factory()->create([
        'current_department_id' => $departmentB->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);
    DocumentTransfer::factory()->create([
        'document_id' => $outgoingDocument->id,
        'from_department_id' => $departmentA->id,
        'to_department_id' => $departmentB->id,
        'forwarded_by_user_id' => $user->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertSee($incomingDocument->tracking_number);
    $response->assertSee($onQueueDocument->tracking_number);
    $response->assertSee($outgoingDocument->tracking_number);
});

test('queue index shows child relation indicator for split-linked queued document', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);
    $parentDocument = Document::factory()->create([
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::Finished,
    ]);
    $childDocument = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    DocumentRelationship::factory()->create([
        'source_document_id' => $childDocument->id,
        'related_document_id' => $parentDocument->id,
        'relation_type' => DocumentRelationshipType::SplitFrom,
    ]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertSee('Relation');
    $response->assertSee('Child');
    $response->assertSee('of '.$parentDocument->tracking_number);
});

test('queue index shows case linked indicator when case has multiple documents without split relationship', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);
    $documentCase = DocumentCase::factory()->create();

    Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::Finished,
    ]);

    Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertSee('Case-linked');
    $response->assertSee($documentCase->case_number);
});

test('queue index hides split link for child document rows', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parentDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::Finished,
    ]);
    $childDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    DocumentRelationship::factory()->create([
        'source_document_id' => $childDocument->id,
        'related_document_id' => $parentDocument->id,
        'relation_type' => DocumentRelationshipType::SplitFrom,
    ]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertSee('Child');
    $response->assertDontSee(route('documents.split.create', $childDocument), false);
});

test('on queue excludes documents assigned to user but in different current department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $departmentA->id, 'role' => UserRole::Regular]);

    $mismatchedDocument = Document::factory()->create([
        'current_department_id' => $departmentB->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertDontSee($mismatchedDocument->tracking_number);
});

test('outgoing queue excludes transfers from users previous department after reassignment', function () {
    $oldDepartment = Department::factory()->create();
    $newDepartment = Department::factory()->create();
    $destinationDepartment = Department::factory()->create();

    $user = User::factory()->create([
        'department_id' => $oldDepartment->id,
        'role' => UserRole::Regular,
    ]);

    $document = Document::factory()->create([
        'current_department_id' => $destinationDepartment->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $oldDepartment->id,
        'to_department_id' => $destinationDepartment->id,
        'forwarded_by_user_id' => $user->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $user->update(['department_id' => $newDepartment->id]);

    $response = $this->actingAs($user)->get(route('documents.queues.index'));

    $response->assertSuccessful();
    $response->assertDontSee($document->tracking_number);
});

test('regular user can process document workflow actions under ownership rules', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $regularUser = User::factory()->create(['department_id' => $fromDepartment->id, 'role' => UserRole::Regular]);
    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $regularUser->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($regularUser)->post(route('documents.forward', $document), [
        'to_department_id' => $toDepartment->id,
    ]);

    $response->assertRedirect();
    expect(DocumentTransfer::query()->where('document_id', $document->id)->exists())->toBeTrue();
});

test('guest user is blocked from queue actions', function () {
    $guestUser = User::factory()->create(['role' => UserRole::Guest]);
    $document = Document::factory()->create();

    $response = $this->actingAs($guestUser)->post(route('documents.accept', $document));

    $response->assertForbidden();
});

test('forward and recall preserve append only transfer history', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $fromDepartment->id]);
    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $this->actingAs($user)->post(route('documents.forward', $document), [
        'to_department_id' => $toDepartment->id,
    ]);

    $transfer = DocumentTransfer::query()->where('document_id', $document->id)->latest('id')->firstOrFail();
    $this->actingAs($user)->post(route('documents.recall', $transfer));

    expect(DocumentTransfer::query()->where('document_id', $document->id)->count())->toBe(1);
    expect($transfer->fresh()->status)->toBe(TransferStatus::Recalled);
});

test('recall voids transfer linked copy inventory and restores source original custody', function () {
    $sourceDepartment = Department::factory()->create();
    $destinationDepartment = Department::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['department_id' => $sourceDepartment->id]);

    $document = Document::factory()->create([
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'original_current_department_id' => $sourceDepartment->id,
        'original_custodian_user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('documents.forward', $document), [
        'to_department_id' => $destinationDepartment->id,
        'forward_version_type' => DocumentVersionType::Original->value,
        'copy_kept' => '1',
        'copy_storage_location' => 'Records Shelf A1',
        'copy_purpose' => 'Pre-acceptance reference',
    ])->assertRedirect();

    $transfer = DocumentTransfer::query()->where('document_id', $document->id)->latest('id')->firstOrFail();

    $this->actingAs($user)->post(route('documents.recall', $transfer))->assertRedirect();

    $document->refresh();
    $copyRecord = DocumentCopy::query()->where('document_transfer_id', $transfer->id)->firstOrFail();

    expect($copyRecord->is_discarded)->toBeTrue();
    expect($copyRecord->discarded_at)->not->toBeNull();
    expect($document->original_current_department_id)->toBe($sourceDepartment->id);
    expect($document->original_custodian_user_id)->toBe($user->id);
});

test('recall of non original forward deactivates destination and retained copy custody artifacts', function () {
    $sourceDepartment = Department::factory()->create();
    $destinationDepartment = Department::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['department_id' => $sourceDepartment->id]);

    $document = Document::factory()->create([
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $this->actingAs($user)->post(route('documents.forward', $document), [
        'to_department_id' => $destinationDepartment->id,
        'forward_version_type' => DocumentVersionType::CertifiedCopy->value,
        'copy_kept' => '1',
        'copy_storage_location' => 'Records Cabinet A-1',
        'copy_purpose' => 'Working reference',
    ])->assertRedirect();

    $transfer = DocumentTransfer::query()->where('document_id', $document->id)->latest('id')->firstOrFail();

    $this->actingAs($user)->post(route('documents.recall', $transfer))->assertRedirect();

    $destinationCustody = DocumentCustody::query()
        ->where('document_id', $document->id)
        ->where('department_id', $destinationDepartment->id)
        ->where('version_type', DocumentVersionType::CertifiedCopy->value)
        ->latest('id')
        ->firstOrFail();

    $sourcePhotocopyCustody = DocumentCustody::query()
        ->where('document_id', $document->id)
        ->where('department_id', $sourceDepartment->id)
        ->where('version_type', DocumentVersionType::Photocopy->value)
        ->latest('id')
        ->firstOrFail();

    expect($destinationCustody->is_current)->toBeFalse();
    expect($destinationCustody->status)->toBe('recalled');
    expect($sourcePhotocopyCustody->is_current)->toBeFalse();
    expect($sourcePhotocopyCustody->status)->toBe('recalled');
});

test('document status and assignee fields stay synchronized after transitions', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $receiver = User::factory()->create(['department_id' => $toDepartment->id]);

    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $forwarder->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $this->actingAs($forwarder)->post(route('documents.forward', $document), [
        'to_department_id' => $toDepartment->id,
    ]);

    $document->refresh();
    expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
    expect($document->current_department_id)->toBe($toDepartment->id);
    expect($document->current_user_id)->toBeNull();

    $this->actingAs($receiver)->post(route('documents.accept', $document));

    $document->refresh();
    expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($document->current_department_id)->toBe($toDepartment->id);
    expect($document->current_user_id)->toBe($receiver->id);
});

test('accept updates received timestamp for incoming department receipt', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $forwarder = User::factory()->create(['department_id' => $fromDepartment->id]);
    $receiver = User::factory()->create(['department_id' => $toDepartment->id]);

    $document = Document::factory()->create([
        'current_department_id' => $toDepartment->id,
        'current_user_id' => null,
        'status' => DocumentWorkflowStatus::Outgoing,
        'received_at' => now()->subDays(2),
    ]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Pending,
        'accepted_at' => null,
    ]);

    $this->actingAs($receiver)->post(route('documents.accept', $document))->assertRedirect();

    $document->refresh();
    expect($document->received_at)->not->toBeNull();
    expect($document->received_at?->gt(now()->subMinutes(1)))->toBeTrue();
});

test('forward with original and kept photocopy creates transfer custody and copy entries', function () {
    $accounting = Department::factory()->create(['name' => 'Accounting']);
    $budget = Department::factory()->create(['name' => 'Budget', 'is_active' => true]);
    $staff = User::factory()->create(['department_id' => $accounting->id]);

    $document = Document::factory()->create([
        'current_department_id' => $accounting->id,
        'current_user_id' => $staff->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($staff)->post(route('documents.forward', $document), [
        'to_department_id' => $budget->id,
        'remarks' => 'Verified supporting documents. Forward to Budget for fund allocation.',
        'forward_version_type' => DocumentVersionType::Original->value,
        'copy_kept' => '1',
        'copy_storage_location' => 'Accounting Cabinet B-2',
        'copy_purpose' => 'For accounting audit trail',
    ]);

    $response->assertRedirect();

    $document->refresh();
    $transfer = DocumentTransfer::query()->where('document_id', $document->id)->latest('id')->firstOrFail();

    expect($transfer->status)->toBe(TransferStatus::Pending);
    expect($transfer->forward_version_type)->toBe(DocumentVersionType::Original);
    expect($transfer->copy_kept)->toBeTrue();
    expect($transfer->copy_storage_location)->toBe('Accounting Cabinet B-2');

    expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
    expect($document->current_department_id)->toBe($budget->id);
    expect($document->current_user_id)->toBeNull();

    expect(DocumentCopy::query()->where('document_id', $document->id)->count())->toBe(1);
    expect(DocumentCopy::query()->where('document_id', $document->id)->first()?->storage_location)->toBe('Accounting Cabinet B-2');

    expect(DocumentCustody::query()->where('document_id', $document->id)->count())->toBeGreaterThan(0);
});
