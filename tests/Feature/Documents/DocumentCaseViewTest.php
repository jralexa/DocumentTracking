<?php

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\DocumentEvent;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Carbon;

test('case detail shows case metrics and timeline events', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 15, 8, 30, 0));

    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);
    $documentCase = DocumentCase::factory()->create();

    $document = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'subject' => 'Case timeline validation document',
        'is_returnable' => true,
        'returned_at' => null,
        'due_at' => now()->subDay(),
    ]);

    DocumentEvent::factory()->create([
        'document_id' => $document->id,
        'acted_by_user_id' => $user->id,
        'event_type' => DocumentEventType::DocumentCreated,
        'message' => 'Document created through add document form.',
        'occurred_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('cases.show', $documentCase))
        ->assertSuccessful()
        ->assertSee('Total Documents')
        ->assertSee('Open Documents')
        ->assertSee('Overdue Documents')
        ->assertSee('Returnable Pending')
        ->assertSee('Returned')
        ->assertSee('Case Timeline')
        ->assertSee('Document Created')
        ->assertSee('Document created through add document form.');

    Carbon::setTestNow();
});

test('case timeline filters by event type tracking and date range', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 15, 12, 0, 0));

    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);
    $documentCase = DocumentCase::factory()->create();

    $matchingDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'tracking_number' => '260215100',
        'subject' => 'Matching case document',
    ]);

    $nonMatchingDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'tracking_number' => '260215101',
        'subject' => 'Non matching case document',
    ]);

    DocumentEvent::factory()->create([
        'document_id' => $matchingDocument->id,
        'acted_by_user_id' => $user->id,
        'event_type' => DocumentEventType::WorkflowForwarded,
        'message' => 'Matching timeline event payload.',
        'occurred_at' => Carbon::create(2026, 2, 15, 10, 0, 0),
    ]);

    DocumentEvent::factory()->create([
        'document_id' => $nonMatchingDocument->id,
        'acted_by_user_id' => $user->id,
        'event_type' => DocumentEventType::RemarkAdded,
        'message' => 'Non matching timeline event payload.',
        'occurred_at' => Carbon::create(2026, 2, 14, 10, 0, 0),
    ]);

    $this->actingAs($user)
        ->get(route('cases.show', [
            'documentCase' => $documentCase,
            'event_type' => DocumentEventType::WorkflowForwarded->value,
            'tracking_number' => '260215100',
            'from_date' => '2026-02-15',
            'to_date' => '2026-02-15',
        ]))
        ->assertSuccessful()
        ->assertSee('Matching timeline event payload.')
        ->assertDontSee('Non matching timeline event payload.');

    Carbon::setTestNow();
});

test('manager can close and reopen a case when all documents are finished', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);
    $documentCase = DocumentCase::factory()->create([
        'status' => 'open',
        'closed_at' => null,
    ]);

    Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'status' => DocumentWorkflowStatus::Finished,
    ]);

    $this->actingAs($manager)
        ->post(route('cases.close', $documentCase))
        ->assertRedirect();

    $documentCase->refresh();
    expect($documentCase->status)->toBe('closed');
    expect($documentCase->closed_at)->not->toBeNull();

    $this->actingAs($manager)
        ->post(route('cases.reopen', $documentCase))
        ->assertRedirect();

    $documentCase->refresh();
    expect($documentCase->status)->toBe('open');
    expect($documentCase->closed_at)->toBeNull();
});

test('manager cannot close case with open documents', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);
    $documentCase = DocumentCase::factory()->create([
        'status' => 'open',
        'closed_at' => null,
    ]);

    Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $this->actingAs($manager)
        ->from(route('cases.show', $documentCase))
        ->post(route('cases.close', $documentCase))
        ->assertRedirect(route('cases.show', $documentCase))
        ->assertSessionHasErrors('case');

    $documentCase->refresh();
    expect($documentCase->status)->toBe('open');
    expect($documentCase->closed_at)->toBeNull();
});

test('guest case index only shows cases opened by the same guest account', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);
    $otherGuest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);

    $ownCase = DocumentCase::factory()->create([
        'case_number' => 'CASE-GUEST-OWN-001',
        'opened_by_user_id' => $guest->id,
    ]);
    $otherCase = DocumentCase::factory()->create([
        'case_number' => 'CASE-GUEST-OTHER-001',
        'opened_by_user_id' => $otherGuest->id,
    ]);

    $response = $this->actingAs($guest)->get(route('cases.index'));

    $response->assertSuccessful();
    $response->assertSee($ownCase->case_number);
    $response->assertDontSee($otherCase->case_number);
});

test('guest cannot open a case owned by another user', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);
    $otherGuest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);

    $otherCase = DocumentCase::factory()->create([
        'opened_by_user_id' => $otherGuest->id,
    ]);

    $this->actingAs($guest)
        ->get(route('cases.show', $otherCase))
        ->assertForbidden();
});
