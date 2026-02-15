<?php

use App\DocumentVersionType;
use App\Models\Department;
use App\Models\Document;
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
});
