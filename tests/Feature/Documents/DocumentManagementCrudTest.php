<?php

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\SystemLog;
use App\Models\User;
use App\UserRole;

test('manager can edit and update document metadata from document management view', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);
    $document = Document::factory()->create([
        'subject' => 'Original Subject',
        'document_type' => 'communication',
        'owner_type' => 'others',
        'owner_name' => 'Original Owner',
        'priority' => 'normal',
        'is_returnable' => false,
    ]);

    $this->actingAs($manager)
        ->get(route('documents.edit', $document))
        ->assertSuccessful()
        ->assertSee('Document Management: Edit');

    $response = $this->actingAs($manager)->put(route('documents.update', $document), [
        'subject' => 'Updated Subject',
        'reference_number' => 'REF-2026-200',
        'document_type' => 'request',
        'owner_type' => 'personal',
        'owner_name' => 'Updated Owner',
        'priority' => 'high',
        'due_at' => now()->addDays(5)->toDateString(),
        'is_returnable' => '1',
        'return_deadline' => now()->addDays(20)->toDateString(),
    ]);

    $response->assertRedirect(route('documents.index'));
    $response->assertSessionHas('status', 'Document updated successfully.');

    $document->refresh();

    expect($document->subject)->toBe('Updated Subject');
    expect($document->reference_number)->toBe('REF-2026-200');
    expect($document->document_type)->toBe('request');
    expect($document->owner_type)->toBe('personal');
    expect($document->owner_name)->toBe('Updated Owner');
    expect($document->priority)->toBe('high');
    expect($document->is_returnable)->toBeTrue();
    expect($document->return_deadline)->not->toBeNull();

    expect(SystemLog::query()
        ->where('action', 'document_metadata_updated')
        ->where('entity_type', 'Document')
        ->where('entity_id', (string) $document->id)
        ->exists())->toBeTrue();
});

test('regular user cannot access document management edit and delete actions', function () {
    $department = Department::factory()->create();
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);
    $document = Document::factory()->create();

    $this->actingAs($regular)
        ->get(route('documents.edit', $document))
        ->assertForbidden();

    $this->actingAs($regular)
        ->put(route('documents.update', $document), [
            'subject' => 'Should Not Update',
            'document_type' => 'request',
            'owner_type' => 'others',
            'owner_name' => 'Denied User',
            'priority' => 'normal',
        ])
        ->assertForbidden();

    $this->actingAs($regular)
        ->delete(route('documents.destroy', $document))
        ->assertForbidden();
});

test('manager can delete document from management view', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);
    $document = Document::factory()->create();
    $item = DocumentItem::factory()->create(['document_id' => $document->id]);

    $response = $this->actingAs($manager)->delete(route('documents.destroy', $document));

    $response->assertRedirect(route('documents.index'));
    $response->assertSessionHas('status');

    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(DocumentItem::query()->whereKey($item->id)->exists())->toBeFalse();

    expect(SystemLog::query()
        ->where('action', 'document_deleted')
        ->where('context->tracking', $document->tracking_number)
        ->exists())->toBeTrue();
});
