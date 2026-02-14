<?php

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\DocumentItem;
use App\Models\User;
use App\UserRole;

test('document creation form is available for processing users', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->get(route('documents.create'));

    $response->assertSuccessful();
    $response->assertSee('Add Document');
});

test('guest role cannot access document creation form', function () {
    $department = Department::factory()->create();
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($guest)->get(route('documents.create'));

    $response->assertForbidden();
});

test('user can create a document from the frontend form', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $payload = [
        'case_title' => 'Salary Claim Batch',
        'subject' => 'Salary Claim of Juan Dela Cruz',
        'reference_number' => 'REF-2026-001',
        'document_type' => 'for_processing',
        'owner_type' => 'school',
        'owner_name' => 'Sample National High School',
        'owner_reference' => 'SCH-001',
        'priority' => 'high',
        'due_at' => now()->addDays(5)->toDateString(),
        'description' => 'Initial submission for accounting review.',
        'item_name' => 'Main Salary Claim Form',
        'initial_remarks' => 'Created by records clerk.',
        'is_returnable' => '1',
        'return_deadline' => now()->addDays(30)->toDateString(),
    ];

    $response = $this->actingAs($user)->post(route('documents.store'), $payload);

    $response->assertRedirect(route('documents.queues.index'));
    $response->assertSessionHas('status');

    $document = Document::query()->first();
    expect($document)->not->toBeNull();
    expect($document?->subject)->toBe($payload['subject']);
    expect($document?->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($document?->current_user_id)->toBe($user->id);
    expect($document?->current_department_id)->toBe($department->id);
    expect($document?->tracking_number)->toMatch('/^\d{9}$/');
    expect($document?->is_returnable)->toBeTrue();
    expect($document?->return_deadline?->toDateString())->toBe($payload['return_deadline']);

    $documentCase = DocumentCase::query()->first();
    expect($documentCase)->not->toBeNull();
    expect($documentCase?->case_number)->toMatch('/^CASE-\d{8}-\d{3}$/');
    expect($documentCase?->title)->toBe($payload['case_title']);

    $documentItem = DocumentItem::query()->first();
    expect($documentItem)->not->toBeNull();
    expect($documentItem?->name)->toBe($payload['item_name']);
    expect($documentItem?->item_type)->toBe('main');

    expect($document?->events()->where('event_type', DocumentEventType::DocumentCreated->value)->exists())->toBeTrue();
    expect($document?->remarks()->where('remark', $payload['initial_remarks'])->exists())->toBeTrue();
});

test('user can create a document in quick add mode with core fields only', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $payload = [
        'quick_mode' => '1',
        'subject' => 'Service Record Request',
        'document_type' => 'request',
        'owner_type' => 'personal',
        'owner_name' => 'Juan Dela Cruz',
    ];

    $response = $this->actingAs($user)->post(route('documents.store'), $payload);

    $response->assertRedirect(route('documents.queues.index'));
    $response->assertSessionHas('status');

    $document = Document::query()->first();
    expect($document)->not->toBeNull();
    expect($document?->subject)->toBe($payload['subject']);
    expect($document?->priority)->toBe('normal');
    expect($document?->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($document?->is_returnable)->toBeFalse();
    expect($document?->current_user_id)->toBe($user->id);
    expect($document?->current_department_id)->toBe($department->id);

    $documentCase = DocumentCase::query()->first();
    expect($documentCase)->not->toBeNull();
    expect($documentCase?->title)->toBe($payload['subject']);
    expect($documentCase?->priority)->toBe('normal');

    $documentItem = DocumentItem::query()->first();
    expect($documentItem)->not->toBeNull();
    expect($documentItem?->name)->toBe($payload['subject']);
});

test('document creation validates required fields', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'subject' => '',
        'document_type' => 'invalid',
        'owner_type' => 'invalid',
        'owner_name' => '',
        'priority' => 'invalid',
    ]);

    $response->assertSessionHasErrors([
        'subject',
        'document_type',
        'owner_type',
        'owner_name',
        'priority',
    ]);
});
