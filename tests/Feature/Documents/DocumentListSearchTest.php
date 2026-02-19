<?php

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\User;
use App\UserRole;

test('regular user can access document list search page', function () {
    $department = Department::factory()->create();
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $this->actingAs($regular)
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertSee('Document List');
});

test('guest user can access document list search page', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);

    $this->actingAs($guest)
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertSee('Document List');
});

test('guest user only sees documents created by the same guest account', function () {
    $department = Department::factory()->create();
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => $department->id,
    ]);
    $otherGuest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => $department->id,
    ]);
    $case = DocumentCase::factory()->create();

    $visibleDocument = Document::factory()->create([
        'document_case_id' => $case->id,
        'tracking_number' => '260217901',
        'subject' => 'Guest Visible Document',
    ]);
    $hiddenDocument = Document::factory()->create([
        'document_case_id' => $case->id,
        'tracking_number' => '260217902',
        'subject' => 'Guest Hidden Document',
    ]);

    $visibleDocument->events()->create([
        'acted_by_user_id' => $guest->id,
        'event_type' => DocumentEventType::DocumentCreated,
        'context' => 'creation',
        'message' => 'Document created through add document form.',
        'occurred_at' => now(),
    ]);
    $hiddenDocument->events()->create([
        'acted_by_user_id' => $otherGuest->id,
        'event_type' => DocumentEventType::DocumentCreated,
        'context' => 'creation',
        'message' => 'Document created through add document form.',
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($guest)->get(route('documents.index'));

    $response->assertSuccessful();
    $response->assertSee($visibleDocument->tracking_number);
    $response->assertDontSee($hiddenDocument->tracking_number);
});

test('document list search filters by tracking and subject', function () {
    $department = Department::factory()->create(['name' => 'Accounting']);
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);
    $case = DocumentCase::factory()->create();

    $matching = Document::factory()->create([
        'document_case_id' => $case->id,
        'tracking_number' => '260215500',
        'subject' => 'Salary Claim - Maria Santos',
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $nonMatching = Document::factory()->create([
        'document_case_id' => $case->id,
        'tracking_number' => '260215501',
        'subject' => 'Medical Reimbursement - Juan Dela Cruz',
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    $response = $this->actingAs($regular)->get(route('documents.index', ['q' => 'Salary Claim']));

    $response->assertSuccessful();
    $response->assertSee($matching->tracking_number);
    $response->assertDontSee($nonMatching->tracking_number);
});

test('document list filter by status works', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);
    $case = DocumentCase::factory()->create();

    $onQueue = Document::factory()->create([
        'document_case_id' => $case->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'tracking_number' => '260215601',
    ]);

    $outgoing = Document::factory()->create([
        'document_case_id' => $case->id,
        'status' => DocumentWorkflowStatus::Outgoing,
        'tracking_number' => '260215602',
    ]);

    $response = $this->actingAs($manager)
        ->get(route('documents.index', ['status' => DocumentWorkflowStatus::OnQueue->value]));

    $response->assertSuccessful();
    $response->assertSee($onQueue->tracking_number);
    $response->assertDontSee($outgoing->tracking_number);
});
