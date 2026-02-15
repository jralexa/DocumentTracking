<?php

use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCopy;
use App\Models\DocumentCustody;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Support\Carbon;

test('regular user cannot access advanced reports', function () {
    $regularUser = User::factory()->create(['role' => UserRole::Regular]);

    $this->actingAs($regularUser)->get(route('reports.aging-overdue'))->assertForbidden();
    $this->actingAs($regularUser)->get(route('reports.performance'))->assertForbidden();
    $this->actingAs($regularUser)->get(route('reports.custody'))->assertForbidden();
});

test('manager can view aging overdue report', function () {
    $department = Department::factory()->create(['name' => 'Accounting']);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);

    $overdueDocument = Document::factory()->create([
        'current_department_id' => $department->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'subject' => 'Overdue Salary Claim',
        'due_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($manager)->get(route('reports.aging-overdue', [
        'department_id' => $department->id,
        'overdue_days' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertSee('Aging / Overdue Report');
    $response->assertSee($overdueDocument->subject);
});

test('manager can view performance report', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 1, 8, 0, 0));

    $department = Department::factory()->create(['name' => 'Accounting']);
    $targetDepartment = Department::factory()->create(['name' => 'Budget']);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);
    $forwarder = User::factory()->create(['department_id' => $department->id, 'name' => 'Alice Processor']);

    $document = Document::factory()->create([
        'current_department_id' => $targetDepartment->id,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);

    DocumentTransfer::factory()->create([
        'document_id' => $document->id,
        'from_department_id' => $department->id,
        'to_department_id' => $targetDepartment->id,
        'forwarded_by_user_id' => $forwarder->id,
        'status' => TransferStatus::Accepted,
        'forwarded_at' => Carbon::create(2026, 2, 10, 8, 0, 0),
        'accepted_at' => Carbon::create(2026, 2, 10, 12, 0, 0),
    ]);

    $response = $this->actingAs($manager)->get(route('reports.performance', [
        'department_id' => $department->id,
        'month' => '2026-02',
    ]));

    Carbon::setTestNow();

    $response->assertSuccessful();
    $response->assertSee('Performance Report');
    $response->assertSee('Alice Processor');
    $response->assertSee('Accounting');
});

test('manager can view custody report', function () {
    $department = Department::factory()->create(['name' => 'Records']);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);

    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'is_returnable' => true,
        'subject' => 'Original Diploma Return',
        'return_deadline' => now()->subDay()->toDateString(),
        'returned_at' => null,
    ]);

    DocumentCustody::factory()->create([
        'document_id' => $document->id,
        'department_id' => $department->id,
        'user_id' => $manager->id,
        'version_type' => DocumentVersionType::Original,
        'is_current' => true,
        'status' => 'in_custody',
        'physical_location' => 'Vault A',
    ]);

    DocumentCopy::factory()->create([
        'document_id' => $document->id,
        'department_id' => $department->id,
        'user_id' => $manager->id,
        'copy_type' => DocumentVersionType::Photocopy,
    ]);

    $response = $this->actingAs($manager)->get(route('reports.custody', [
        'department_id' => $department->id,
    ]));

    $response->assertSuccessful();
    $response->assertSee('Custody Report');
    $response->assertSee('Original Diploma Return');
    $response->assertSee('Vault A');
});
