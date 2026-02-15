<?php

use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\User;
use App\UserRole;

test('regular user can access original custody and copy inventory pages', function () {
    $department = Department::factory()->create();
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $this->actingAs($regular)
        ->get(route('custody.originals.index'))
        ->assertSuccessful()
        ->assertSee('Original Custody');

    $this->actingAs($regular)
        ->get(route('custody.copies.index'))
        ->assertSuccessful()
        ->assertSee('Copy Inventory');
});

test('guest user cannot access custody and copies pages', function () {
    $guest = User::factory()->create([
        'role' => UserRole::Guest,
    ]);

    $this->actingAs($guest)->get(route('custody.originals.index'))->assertForbidden();
    $this->actingAs($guest)->get(route('custody.copies.index'))->assertForbidden();
});

test('manager can access returnable documents page', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);

    $this->actingAs($manager)
        ->get(route('custody.returnables.index'))
        ->assertSuccessful()
        ->assertSee('Returnable Documents');
});

test('regular user cannot access returnable documents page', function () {
    $department = Department::factory()->create();
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $this->actingAs($regular)
        ->get(route('custody.returnables.index'))
        ->assertForbidden();
});

test('manager can mark returnable document as returned', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);

    $document = Document::factory()->create([
        'is_returnable' => true,
        'return_deadline' => now()->addDays(5)->toDateString(),
        'original_current_department_id' => $department->id,
        'original_custodian_user_id' => $manager->id,
        'original_physical_location' => 'Vault 1',
        'returned_at' => null,
    ]);

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $department->id,
        'user_id' => $manager->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
    ]);

    $response = $this->actingAs($manager)->post(route('custody.returnables.returned', $document), [
        'returned_to' => 'Juan Dela Cruz',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $document->refresh();

    expect($document->returned_to)->toBe('Juan Dela Cruz');
    expect($document->returned_at)->not->toBeNull();
    expect($document->original_current_department_id)->toBeNull();
    expect($document->original_custodian_user_id)->toBeNull();
    expect($document->original_physical_location)->toBeNull();
    expect($document->status)->toBe(DocumentWorkflowStatus::Finished);
    expect($document->completed_at)->not->toBeNull();
    expect($document->current_department_id)->toBeNull();
    expect($document->current_user_id)->toBeNull();
});

test('copy inventory page excludes discarded copy records by default', function () {
    $department = Department::factory()->create();
    $regular = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $activeDocument = Document::factory()->create(['tracking_number' => '260215111']);
    $discardedDocument = Document::factory()->create(['tracking_number' => '260215222']);

    DocumentCopy::factory()->create([
        'document_id' => $activeDocument->id,
        'department_id' => $department->id,
        'user_id' => $regular->id,
        'is_discarded' => false,
    ]);

    DocumentCopy::factory()->create([
        'document_id' => $discardedDocument->id,
        'department_id' => $department->id,
        'user_id' => $regular->id,
        'is_discarded' => true,
        'discarded_at' => now(),
    ]);

    $this->actingAs($regular)
        ->get(route('custody.copies.index'))
        ->assertSuccessful()
        ->assertSee('260215111')
        ->assertDontSee('260215222');
});

test('current original holder can release original custody to another department', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $destinationDepartment = Department::factory()->create(['name' => 'Budget Section']);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $sourceDepartment->id,
    ]);

    $document = Document::factory()->create([
        'original_current_department_id' => $sourceDepartment->id,
        'original_custodian_user_id' => $user->id,
        'original_physical_location' => 'Records Cabinet A-1',
    ]);

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $sourceDepartment->id,
        'user_id' => $user->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
    ]);

    $response = $this->actingAs($user)->post(route('custody.originals.release', $document), [
        'to_department_id' => $destinationDepartment->id,
        'copy_kept' => '1',
        'copy_storage_location' => 'Records Cabinet B-2',
        'copy_purpose' => 'Office reference',
        'remarks' => 'Release original to Budget for final signing.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $document->refresh();

    expect($document->original_current_department_id)->toBe($destinationDepartment->id);
    expect($document->original_custodian_user_id)->toBeNull();
    expect($document->original_physical_location)->toBe('Records Cabinet A-1');

    expect(DocumentCopy::query()
        ->where('document_id', $document->id)
        ->where('department_id', $sourceDepartment->id)
        ->where('storage_location', 'Records Cabinet B-2')
        ->where('is_discarded', false)
        ->exists())->toBeTrue();
});

test('non holder department cannot release original custody', function () {
    $sourceDepartment = Department::factory()->create(['name' => 'Records Section']);
    $destinationDepartment = Department::factory()->create(['name' => 'Budget Section']);
    $otherDepartment = Department::factory()->create(['name' => 'Accounting Section']);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $otherDepartment->id,
    ]);

    $document = Document::factory()->create([
        'original_current_department_id' => $sourceDepartment->id,
    ]);

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $sourceDepartment->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
    ]);

    $response = $this->actingAs($user)
        ->from(route('custody.originals.index'))
        ->post(route('custody.originals.release', $document), [
            'to_department_id' => $destinationDepartment->id,
        ]);

    $response->assertRedirect(route('custody.originals.index'));
    $response->assertSessionHasErrors('release_original');

    $document->refresh();

    expect($document->original_current_department_id)->toBe($sourceDepartment->id);
});
