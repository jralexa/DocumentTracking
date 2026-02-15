<?php

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

test('document can be linked to an existing case during intake', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);
    $existingCase = DocumentCase::factory()->create([
        'status' => 'open',
    ]);

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'quick_mode' => '1',
        'case_mode' => 'existing',
        'document_case_id' => $existingCase->id,
        'subject' => 'Additional supporting document',
        'document_type' => 'submission',
        'owner_type' => 'personal',
        'owner_name' => 'Maria L. Santos',
    ]);

    $response->assertRedirect(route('documents.queues.index'));

    $document = Document::query()->firstOrFail();
    expect($document->document_case_id)->toBe($existingCase->id);
});

test('assignee can split parent document into routed child documents', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $hr = Department::factory()->create(['name' => 'HR Unit', 'is_active' => true]);
    $medical = Department::factory()->create(['name' => 'Medical Unit', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'tracking_number' => '260214033',
        'subject' => 'Employment Requirements Envelope - Juan P. Dela Cruz',
    ]);

    $response = $this->actingAs($user)->post(route('documents.split.store', $parent), [
        'confirm_routing_reviewed' => '1',
        'children' => [
            [
                'subject' => 'Birth Certificate (Original) - Juan P. Dela Cruz',
                'document_type' => 'submission',
                'owner_type' => 'personal',
                'owner_name' => 'Juan P. Dela Cruz',
                'to_department_ids' => [$hr->id],
                'is_returnable' => '1',
                'return_deadline' => now()->addMonth()->toDateString(),
                'remarks' => 'Forward to HR.',
            ],
            [
                'subject' => 'Medical Certificate - Juan P. Dela Cruz',
                'document_type' => 'submission',
                'owner_type' => 'personal',
                'owner_name' => 'Juan P. Dela Cruz',
                'to_department_ids' => [$medical->id],
                'remarks' => 'Forward to Medical.',
            ],
        ],
    ]);

    $response->assertRedirect(route('cases.show', $documentCase));

    $children = Document::query()
        ->where('document_case_id', $documentCase->id)
        ->where('id', '!=', $parent->id)
        ->orderBy('id')
        ->get();

    expect($children)->toHaveCount(2);
    expect($children[0]->status)->toBe(DocumentWorkflowStatus::Outgoing);
    expect($children[0]->current_department_id)->toBe($hr->id);
    expect($children[0]->metadata['display_tracking'])->toBe('260214033-A');
    expect($children[1]->current_department_id)->toBe($medical->id);
    expect($children[1]->metadata['display_tracking'])->toBe('260214033-B');

    expect(DocumentTransfer::query()->whereIn('document_id', $children->pluck('id'))->count())->toBe(2);
    expect(DocumentTransfer::query()->whereIn('document_id', $children->pluck('id'))->where('status', TransferStatus::Pending->value)->count())->toBe(2);

    expect(DocumentRelationship::query()
        ->whereIn('source_document_id', $children->pluck('id'))
        ->where('related_document_id', $parent->id)
        ->where('relation_type', DocumentRelationshipType::SplitFrom->value)
        ->count())->toBe(2);
});

test('non assignee cannot open split wizard', function () {
    $department = Department::factory()->create();
    $assignee = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);
    $otherUser = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);

    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $assignee->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $this->actingAs($otherUser)
        ->get(route('documents.split.create', $document))
        ->assertForbidden();
});

test('split wizard starts from next available suffix for existing split parent', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id, 'role' => UserRole::Regular]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'tracking_number' => '260214033',
    ]);

    $existingChild = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'metadata' => [
            'display_tracking' => '260214033-A',
            'split_suffix' => 'A',
            'parent_tracking_number' => '260214033',
            'parent_document_id' => $parent->id,
        ],
    ]);

    DocumentRelationship::factory()->create([
        'source_document_id' => $existingChild->id,
        'related_document_id' => $parent->id,
        'relation_type' => DocumentRelationshipType::SplitFrom,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.split.create', $parent))
        ->assertSuccessful()
        ->assertSee('Auto suffix starts from: B');
});

test('single child row can route to multiple departments', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $hr = Department::factory()->create(['name' => 'HR Unit', 'is_active' => true]);
    $admin = Department::factory()->create(['name' => 'Admin Unit', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'tracking_number' => '260214099',
    ]);

    $response = $this->actingAs($user)->post(route('documents.split.store', $parent), [
        'confirm_routing_reviewed' => '1',
        'children' => [
            [
                'subject' => 'NBI Clearance - Juan P. Dela Cruz',
                'document_type' => 'submission',
                'owner_type' => 'personal',
                'owner_name' => 'Juan P. Dela Cruz',
                'forward_version_type' => DocumentVersionType::Photocopy->value,
                'original_storage_location' => 'Records Vault Shelf A',
                'to_department_ids' => [$hr->id, $admin->id],
            ],
        ],
    ]);

    $response->assertRedirect(route('cases.show', $documentCase));

    $children = Document::query()
        ->where('document_case_id', $documentCase->id)
        ->where('id', '!=', $parent->id)
        ->orderBy('id')
        ->get();

    expect($children)->toHaveCount(2);
    expect($children->pluck('current_department_id')->all())->toEqualCanonicalizing([$hr->id, $admin->id]);
    expect($children->pluck('metadata.display_tracking')->all())->toEqualCanonicalizing(['260214099-A', '260214099-B']);
});

test('split child can inherit owner from parent by default', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $destinationDepartment = Department::factory()->create(['name' => 'Admin Unit', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'owner_type' => 'school',
        'owner_name' => 'Hinunangan NHS',
    ]);

    $response = $this->actingAs($user)->post(route('documents.split.store', $parent), [
        'confirm_routing_reviewed' => '1',
        'children' => [
            [
                'subject' => 'School Endorsement - Reclassification',
                'document_type' => 'submission',
                'same_owner_as_parent' => '1',
                'to_department_ids' => [$destinationDepartment->id],
            ],
        ],
    ]);

    $response->assertRedirect(route('cases.show', $documentCase));

    $child = Document::query()
        ->where('document_case_id', $documentCase->id)
        ->where('id', '!=', $parent->id)
        ->firstOrFail();

    expect($child->owner_type)->toBe('school');
    expect($child->owner_name)->toBe('Hinunangan NHS');
});

test('split can forward photocopy while keeping original in source custody', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $destinationDepartment = Department::factory()->create(['name' => 'ICT Unit', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'tracking_number' => '260214120',
    ]);

    $response = $this->actingAs($user)->post(route('documents.split.store', $parent), [
        'confirm_routing_reviewed' => '1',
        'children' => [
            [
                'subject' => 'NBI Clearance - Juan P. Dela Cruz',
                'document_type' => 'submission',
                'owner_type' => 'personal',
                'owner_name' => 'Juan P. Dela Cruz',
                'forward_version_type' => DocumentVersionType::Photocopy->value,
                'to_department_ids' => [$destinationDepartment->id],
                'original_storage_location' => 'Records Vault Shelf A',
                'copy_kept' => '1',
                'copy_storage_location' => 'Records Cabinet B-2',
                'copy_purpose' => 'Audit trail',
            ],
        ],
    ]);

    $response->assertRedirect(route('cases.show', $documentCase));

    $child = Document::query()
        ->where('document_case_id', $documentCase->id)
        ->where('id', '!=', $parent->id)
        ->firstOrFail();

    $transfer = DocumentTransfer::query()->where('document_id', $child->id)->firstOrFail();

    expect($transfer->forward_version_type)->toBe(DocumentVersionType::Photocopy);
    expect($transfer->copy_kept)->toBeTrue();
    expect($child->original_current_department_id)->toBe($sourceDepartment->id);
    expect($child->original_custodian_user_id)->toBe($user->id);
    expect($child->original_physical_location)->toBe('Records Vault Shelf A');

    expect(DocumentCustody::query()
        ->where('document_id', $child->id)
        ->where('version_type', DocumentVersionType::Original->value)
        ->where('department_id', $sourceDepartment->id)
        ->exists())->toBeTrue();

    expect(DocumentCustody::query()
        ->where('document_id', $child->id)
        ->where('version_type', DocumentVersionType::Photocopy->value)
        ->where('department_id', $destinationDepartment->id)
        ->exists())->toBeTrue();

    expect(DocumentCopy::query()
        ->where('document_id', $child->id)
        ->where('department_id', $sourceDepartment->id)
        ->where('storage_location', 'Records Cabinet B-2')
        ->exists())->toBeTrue();
});

test('original split version cannot route to multiple departments', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $hr = Department::factory()->create(['name' => 'HR Unit', 'is_active' => true]);
    $admin = Department::factory()->create(['name' => 'Admin Unit', 'is_active' => true]);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);
    $documentCase = DocumentCase::factory()->create(['status' => 'open']);

    $parent = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $sourceDepartment->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($user)->from(route('documents.split.create', $parent))
        ->post(route('documents.split.store', $parent), [
            'confirm_routing_reviewed' => '1',
            'children' => [
                [
                    'subject' => 'Original Branch',
                    'document_type' => 'submission',
                    'owner_type' => 'personal',
                    'owner_name' => 'Juan P. Dela Cruz',
                    'forward_version_type' => DocumentVersionType::Original->value,
                    'to_department_ids' => [$hr->id, $admin->id],
                ],
            ],
        ]);

    $response->assertRedirect(route('documents.split.create', $parent));
    $response->assertSessionHasErrors('children.0.to_department_ids');
});
