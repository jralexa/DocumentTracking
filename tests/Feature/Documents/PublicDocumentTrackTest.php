<?php

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCase;

test('public track page can be opened without authentication', function () {
    $this->get(route('documents.track.public'))
        ->assertSuccessful()
        ->assertSee('Public Document Tracker');
});

test('public tracker can find document by tracking number', function () {
    $department = Department::factory()->create(['name' => 'Accounting']);

    Document::factory()->create([
        'document_case_id' => DocumentCase::factory()->create()->id,
        'tracking_number' => '260215200',
        'subject' => 'Salary Adjustment Request',
        'status' => DocumentWorkflowStatus::Outgoing,
        'current_department_id' => $department->id,
    ]);

    $this->get(route('documents.track.public', ['tracking_number' => '260215200']))
        ->assertSuccessful()
        ->assertSee('Salary Adjustment Request')
        ->assertSee('Current office: Accounting')
        ->assertDontSee('Copy Inventory')
        ->assertDontSee('Custody Records');
});
