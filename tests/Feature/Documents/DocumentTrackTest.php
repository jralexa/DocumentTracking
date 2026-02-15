<?php

use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;

test('guest role can access track document page', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);

    $this->actingAs($guest)
        ->get(route('documents.track'))
        ->assertSuccessful()
        ->assertSee('Track Document');
});

test('user can track document by base tracking number', function () {
    $fromDepartment = Department::factory()->create(['name' => 'Records']);
    $toDepartment = Department::factory()->create(['name' => 'Accounting']);
    $user = User::factory()->create(['role' => UserRole::Regular, 'department_id' => $fromDepartment->id]);

    $document = Document::factory()->create([
        'document_case_id' => DocumentCase::factory()->create()->id,
        'tracking_number' => '260215001',
        'subject' => 'Salary Claim of Juan Dela Cruz',
        'status' => DocumentWorkflowStatus::Outgoing,
        'current_department_id' => $toDepartment->id,
        'current_user_id' => null,
    ]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $fromDepartment->id,
        'to_department_id' => $toDepartment->id,
        'forwarded_by_user_id' => $user->id,
        'status' => TransferStatus::Pending,
        'forward_version_type' => DocumentVersionType::Original,
        'forwarded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('documents.track', ['tracking_number' => '260215001']))
        ->assertSuccessful()
        ->assertSee('Salary Claim of Juan Dela Cruz')
        ->assertSee('260215001')
        ->assertSee('Routing Timeline');
});

test('user can track split child by display tracking number', function () {
    $department = Department::factory()->create(['name' => 'HR']);
    $user = User::factory()->create(['role' => UserRole::Regular, 'department_id' => $department->id]);

    Document::factory()->create([
        'document_case_id' => DocumentCase::factory()->create()->id,
        'tracking_number' => '260215010',
        'subject' => 'Birth Certificate - Juan P. Dela Cruz',
        'status' => DocumentWorkflowStatus::Outgoing,
        'current_department_id' => $department->id,
        'metadata' => [
            'display_tracking' => '260214033-A',
            'split_suffix' => 'A',
            'parent_tracking_number' => '260214033',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('documents.track', ['tracking_number' => '260214033-A']))
        ->assertSuccessful()
        ->assertSee('Birth Certificate - Juan P. Dela Cruz')
        ->assertSee('260214033-A');
});

test('track page shows not found message for unknown tracking number', function () {
    $user = User::factory()->create(['role' => UserRole::Regular]);

    $this->actingAs($user)
        ->get(route('documents.track', ['tracking_number' => '999999999']))
        ->assertSuccessful()
        ->assertSee('No document found for tracking number');
});
