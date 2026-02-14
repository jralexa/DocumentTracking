<?php

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\DocumentItem;
use App\Models\User;

test('a document belongs to a case and tracks current handlers', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $documentCase = DocumentCase::factory()->create();

    $document = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
    ]);

    expect($document->documentCase->is($documentCase))->toBeTrue();
    expect($document->currentDepartment->is($department))->toBeTrue();
    expect($document->currentUser->is($user))->toBeTrue();
    expect($documentCase->documents()->whereKey($document->id)->exists())->toBeTrue();
});

test('document items support parent and child relationships', function () {
    $document = Document::factory()->create();
    $parentItem = DocumentItem::factory()->create([
        'document_id' => $document->id,
        'item_type' => 'main',
    ]);
    $childItem = DocumentItem::factory()->create([
        'document_id' => $document->id,
        'parent_item_id' => $parentItem->id,
        'item_type' => 'attachment',
    ]);

    expect($childItem->parentItem->is($parentItem))->toBeTrue();
    expect($parentItem->childItems()->whereKey($childItem->id)->exists())->toBeTrue();
    expect($document->rootItems()->whereKey($parentItem->id)->exists())->toBeTrue();
    expect($document->rootItems()->whereKey($childItem->id)->exists())->toBeFalse();
});

test('document tracking number must be unique', function () {
    $trackingNumber = now()->format('ymd').'999';

    Document::factory()->create(['tracking_number' => $trackingNumber]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    Document::factory()->create(['tracking_number' => $trackingNumber]);
});
