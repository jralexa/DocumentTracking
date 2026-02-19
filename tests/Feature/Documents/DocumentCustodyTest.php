<?php

use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCustody;
use App\Models\User;
use App\Services\DocumentCustodyService;
use Illuminate\Support\Carbon;

test('assigning original custody deactivates previous original holder and syncs document fields', function () {
    $service = app(DocumentCustodyService::class);
    $previousDepartment = Department::factory()->create();
    $newDepartment = Department::factory()->create();
    $previousCustodian = User::factory()->create(['department_id' => $previousDepartment->id]);
    $newCustodian = User::factory()->create(['department_id' => $newDepartment->id]);
    $document = Document::factory()->create();

    $previousOriginal = DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $previousDepartment->id,
        'user_id' => $previousCustodian->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
        'released_at' => null,
    ]);

    $newOriginal = $service->assignOriginalCustody(
        document: $document,
        department: $newDepartment,
        custodian: $newCustodian,
        physicalLocation: 'Cabinet A / Drawer 3',
        storageReference: 'FOLDER-001',
        purpose: 'Primary custody transfer'
    );

    $previousOriginal->refresh();
    $document->refresh();

    expect($previousOriginal->is_current)->toBeFalse();
    expect($previousOriginal->status)->toBe('forwarded');
    expect($newOriginal->version_type)->toBe(DocumentVersionType::Original);
    expect($newOriginal->is_current)->toBeTrue();
    expect($document->original_current_department_id)->toBe($newDepartment->id);
    expect($document->original_custodian_user_id)->toBe($newCustodian->id);
    expect($document->original_physical_location)->toBe('Cabinet A / Drawer 3');
});

test('recording derivative custody creates copy without changing current original tracker fields', function () {
    $service = app(DocumentCustodyService::class);
    $document = Document::factory()->create([
        'original_current_department_id' => null,
        'original_custodian_user_id' => null,
    ]);
    $department = Department::factory()->create();
    $custodian = User::factory()->create(['department_id' => $department->id]);

    $copyCustody = $service->recordDerivativeCustody(
        document: $document,
        versionType: DocumentVersionType::Photocopy,
        department: $department,
        custodian: $custodian,
        physicalLocation: 'Shelf B',
        purpose: 'Accounting archive'
    );

    $document->refresh();

    expect($copyCustody->version_type)->toBe(DocumentVersionType::Photocopy);
    expect($copyCustody->is_current)->toBeTrue();
    expect($document->original_current_department_id)->toBeNull();
    expect($document->original_custodian_user_id)->toBeNull();
});

test('marking original returned clears active original tracker and stamps return fields', function () {
    $service = app(DocumentCustodyService::class);
    $department = Department::factory()->create();
    $custodian = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'is_returnable' => true,
        'original_current_department_id' => $department->id,
        'original_custodian_user_id' => $custodian->id,
        'original_physical_location' => 'Vault 1',
    ]);

    $originalCustody = DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $department->id,
        'user_id' => $custodian->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
        'released_at' => null,
    ]);

    $returnedAt = Carbon::create(2026, 2, 14, 10, 30, 0);
    $service->markOriginalReturned($document, $custodian, 'Juan Dela Cruz', $returnedAt);

    $document->refresh();
    $originalCustody->refresh();

    expect($document->returned_to)->toBe('Juan Dela Cruz');
    expect($document->returned_at?->equalTo($returnedAt))->toBeTrue();
    expect($document->original_current_department_id)->toBeNull();
    expect($document->original_custodian_user_id)->toBeNull();
    expect($document->original_physical_location)->toBeNull();
    expect($document->status)->toBe(DocumentWorkflowStatus::Finished);
    expect($document->completed_at?->equalTo($returnedAt))->toBeTrue();
    expect($document->current_department_id)->toBeNull();
    expect($document->current_user_id)->toBeNull();
    expect($originalCustody->is_current)->toBeFalse();
    expect($originalCustody->status)->toBe('returned');
});

test('document current original custody relationship returns active original record only', function () {
    $document = Document::factory()->create();

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => false,
        'status' => 'forwarded',
    ]);

    $activeOriginal = DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
    ]);

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'version_type' => DocumentVersionType::Photocopy,
        'is_current' => true,
    ]);

    $document->refresh();

    expect($document->currentOriginalCustody?->is($activeOriginal))->toBeTrue();
});
