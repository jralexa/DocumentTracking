<?php

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\UserRole;

test('forwarding stores dispatch metadata fields', function () {
    $fromDepartment = Department::factory()->create();
    $toDepartment = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $fromDepartment->id,
    ]);

    $document = Document::factory()->create([
        'current_department_id' => $fromDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($user)->post(route('documents.forward', $document), [
        'to_department_id' => $toDepartment->id,
        'remarks' => 'Forwarded with dispatch details.',
        'dispatch_method' => 'courier',
        'dispatch_reference' => 'COURIER-REF-2026-031',
        'release_receipt_reference' => 'RR-2026-009',
    ]);

    $response->assertRedirect();

    $transfer = DocumentTransfer::query()->latest('id')->firstOrFail();

    expect($transfer->dispatch_method)->toBe('courier');
    expect($transfer->dispatch_reference)->toBe('COURIER-REF-2026-031');
    expect($transfer->release_receipt_reference)->toBe('RR-2026-009');
    expect($transfer->remarks)->toBe('Forwarded with dispatch details.');
});
