<?php

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentCase;
use App\Models\DocumentItem;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('guest role can access document creation form', function () {
    $department = Department::factory()->create();
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($guest)->get(route('documents.create'));

    $response->assertSuccessful();
});

test('guest create form only lists open cases owned by that guest', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);
    $otherGuest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);

    DocumentCase::factory()->create([
        'case_number' => 'CASE-GUEST-FORM-OWN',
        'status' => 'open',
        'opened_by_user_id' => $guest->id,
    ]);
    DocumentCase::factory()->create([
        'case_number' => 'CASE-GUEST-FORM-OTHER',
        'status' => 'open',
        'opened_by_user_id' => $otherGuest->id,
    ]);

    $response = $this->actingAs($guest)->get(route('documents.create'));

    $response->assertSuccessful();
    $response->assertSee('CASE-GUEST-FORM-OWN');
    $response->assertDontSee('CASE-GUEST-FORM-OTHER');
});

test('guest role can create document from frontend form', function () {
    $recordsDepartment = Department::factory()->create([
        'code' => 'RECORDS',
        'name' => 'Records Section',
        'is_active' => true,
    ]);

    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);

    $payload = [
        'quick_mode' => '1',
        'subject' => 'Incoming communication for records',
        'document_type' => 'communication',
        'owner_type' => 'others',
        'owner_name' => 'External Office',
    ];

    $response = $this->actingAs($guest)->post(route('documents.store'), $payload);

    $response->assertRedirect(route('documents.create'));
    $response->assertSessionHas('status');

    $document = Document::query()->first();
    expect($document)->not->toBeNull();
    $documentCase = DocumentCase::query()->first();
    expect($documentCase)->not->toBeNull();
    expect($document?->current_user_id)->toBeNull();
    expect($document?->current_department_id)->toBe($recordsDepartment->id);
    expect($document?->original_current_department_id)->toBe($recordsDepartment->id);
    expect($document?->original_custodian_user_id)->toBeNull();
    expect($document?->status)->toBe(DocumentWorkflowStatus::Outgoing);
    expect($documentCase?->opened_by_user_id)->toBe($guest->id);
    expect($document?->transfers()->count())->toBe(1);
    expect($document?->transfers()->first()?->status)->toBe(TransferStatus::Pending);
    expect($document?->transfers()->first()?->to_department_id)->toBe($recordsDepartment->id);
    expect($document?->currentOriginalCustody()->exists())->toBeTrue();
});

test('user can create a document from the frontend form', function () {
    Storage::fake('public');

    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $attachmentOne = UploadedFile::fake()->create('appointment-paper.pdf', 120, 'application/pdf');
    $attachmentTwo = UploadedFile::fake()->create('service-record.xlsx', 90, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

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
        'source_channel' => 'courier',
        'document_classification' => 'confidential',
        'routing_slip_number' => 'RS-2026-001',
        'control_number' => 'CTRL-2026-001',
        'received_by_name' => 'Records Clerk One',
        'received_at' => now()->subHour()->toDateTimeString(),
        'sla_days' => 4,
        'description' => 'Initial submission for accounting review.',
        'item_name' => 'Main Salary Claim Form',
        'initial_remarks' => 'Created by records clerk.',
        'is_returnable' => '1',
        'return_deadline' => now()->addDays(30)->toDateString(),
        'attachments' => [$attachmentOne, $attachmentTwo],
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
    expect($document?->original_current_department_id)->toBe($department->id);
    expect($document?->original_custodian_user_id)->toBe($user->id);
    expect($document?->tracking_number)->toMatch('/^\d{9}$/');
    expect($document?->is_returnable)->toBeTrue();
    expect($document?->return_deadline?->toDateString())->toBe($payload['return_deadline']);
    expect($document?->metadata)->toMatchArray([
        'source_channel' => 'courier',
        'document_classification' => 'confidential',
        'routing_slip_number' => 'RS-2026-001',
        'control_number' => 'CTRL-2026-001',
        'received_by_name' => 'Records Clerk One',
        'sla_days' => 4,
        'sla_applied' => false,
    ]);

    $documentCase = DocumentCase::query()->first();
    expect($documentCase)->not->toBeNull();
    expect($documentCase?->case_number)->toMatch('/^CASE-\d{8}-\d{3}$/');
    expect($documentCase?->title)->toBe($payload['case_title']);
    expect($documentCase?->opened_by_user_id)->toBe($user->id);

    $documentItem = DocumentItem::query()->first();
    expect($documentItem)->not->toBeNull();
    expect($documentItem?->name)->toBe($payload['item_name']);
    expect($documentItem?->item_type)->toBe('main');

    expect(DocumentAttachment::query()->where('document_id', $document?->id)->count())->toBe(2);

    $storedAttachments = DocumentAttachment::query()
        ->where('document_id', $document?->id)
        ->get();

    $storedAttachments->each(function (DocumentAttachment $attachment): void {
        Storage::disk($attachment->disk)->assertExists($attachment->path);
    });

    expect(
        DocumentItem::query()
            ->where('document_id', $document?->id)
            ->where('item_type', 'attachment')
            ->count()
    )->toBe(2);

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
    expect($document?->original_current_department_id)->toBe($department->id);
    expect($document?->original_custodian_user_id)->toBe($user->id);
    expect($document?->currentOriginalCustody()->exists())->toBeTrue();

    $documentCase = DocumentCase::query()->first();
    expect($documentCase)->not->toBeNull();
    expect($documentCase?->title)->toBe($payload['subject']);
    expect($documentCase?->priority)->toBe('normal');

    $documentItem = DocumentItem::query()->first();
    expect($documentItem)->not->toBeNull();
    expect($documentItem?->name)->toBe($payload['subject']);
});

test('full intake applies SLA due date when due date is not provided', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'subject' => 'Salary Differential Request',
        'document_type' => 'request',
        'owner_type' => 'personal',
        'owner_name' => 'Ana Reyes',
        'priority' => 'high',
        'source_channel' => 'walk_in',
        'document_classification' => 'routine',
    ]);

    $response->assertRedirect(route('documents.queues.index'));

    $document = Document::query()->firstOrFail();

    expect($document->due_at)->not->toBeNull();
    expect($document->metadata['sla_applied'] ?? false)->toBeTrue();
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

test('guest intake always routes to records even when guest has an assigned department', function () {
    $recordsDepartment = Department::factory()->create([
        'code' => 'RECORDS',
        'name' => 'Records Section',
        'is_active' => true,
    ]);
    $otherDepartment = Department::factory()->create([
        'code' => 'HRMO',
        'name' => 'Human Resource Management Office',
        'is_active' => true,
    ]);

    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => $otherDepartment->id,
    ]);

    $response = $this->actingAs($guest)->post(route('documents.store'), [
        'quick_mode' => '1',
        'subject' => 'Personnel encoded letter',
        'document_type' => 'communication',
        'owner_type' => 'others',
        'owner_name' => 'External Sender',
    ]);

    $response->assertRedirect(route('documents.create'));

    $document = Document::query()->firstOrFail();
    expect($document->current_department_id)->toBe($recordsDepartment->id);
    expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
});

test('guest can link new document to own open case only', function () {
    $recordsDepartment = Department::factory()->create([
        'code' => 'RECORDS',
        'name' => 'Records Section',
        'is_active' => true,
    ]);

    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);
    $otherGuest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);

    $ownCase = DocumentCase::factory()->create([
        'status' => 'open',
        'owner_type' => 'others',
        'owner_name' => 'Owner A',
        'owner_reference' => 'REF-A',
        'opened_by_user_id' => $guest->id,
    ]);
    $otherCase = DocumentCase::factory()->create([
        'status' => 'open',
        'owner_type' => 'others',
        'owner_name' => 'Owner B',
        'owner_reference' => 'REF-B',
        'opened_by_user_id' => $otherGuest->id,
    ]);

    $this->actingAs($guest)->post(route('documents.store'), [
        'quick_mode' => '1',
        'case_mode' => 'existing',
        'document_case_id' => $ownCase->id,
        'subject' => 'Own case supporting document',
        'document_type' => 'submission',
        'owner_type' => 'personal',
        'owner_name' => 'Ignored by existing mode',
    ])->assertRedirect(route('documents.create'));

    $linkedDocument = Document::query()->firstOrFail();
    expect($linkedDocument->document_case_id)->toBe($ownCase->id);
    expect($linkedDocument->owner_name)->toBe($ownCase->owner_name);

    $this->actingAs($guest)->post(route('documents.store'), [
        'quick_mode' => '1',
        'case_mode' => 'existing',
        'document_case_id' => $otherCase->id,
        'subject' => 'Unauthorized case link',
        'document_type' => 'submission',
        'owner_type' => 'personal',
        'owner_name' => 'Unauthorized',
    ])->assertSessionHasErrors('document_case_id');

    expect(Document::query()->count())->toBe(1);
    expect($linkedDocument->transfers()->first()?->to_department_id)->toBe($recordsDepartment->id);
});

test('record and add another redirects back with intake prefill payload', function () {
    Department::factory()->create([
        'code' => 'RECORDS',
        'name' => 'Records Section',
        'is_active' => true,
    ]);

    $guest = User::factory()->create([
        'role' => UserRole::Guest,
        'department_id' => null,
    ]);

    $response = $this->actingAs($guest)->post(route('documents.store'), [
        'quick_mode' => '1',
        'add_another' => '1',
        'subject' => 'Batch doc one',
        'document_type' => 'request',
        'owner_type' => 'others',
        'owner_name' => 'Batch Owner',
        'source_channel' => 'email',
        'document_classification' => 'urgent',
    ]);

    $response->assertRedirect(route('documents.create'));
    $response->assertSessionHas('intake_prefill', function (array $prefill): bool {
        return ($prefill['document_type'] ?? null) === 'request'
            && ($prefill['owner_name'] ?? null) === 'Batch Owner'
            && ($prefill['source_channel'] ?? null) === 'email'
            && isset($prefill['preferred_case_id']);
    });
});
