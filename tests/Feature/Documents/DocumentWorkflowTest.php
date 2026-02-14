<?php

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
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
