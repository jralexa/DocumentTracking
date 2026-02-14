<?php

use App\DocumentWorkflowStatus;
use App\Jobs\GenerateDepartmentMonthlyReportsJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

test('regular user cannot access monthly department reports', function () {
    $regularUser = User::factory()->create(['role' => UserRole::Regular]);

    $response = $this->actingAs($regularUser)->get(route('reports.departments.monthly'));

    $response->assertForbidden();
});

test('manager can view monthly report metrics', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 1, 8, 0, 0));

    $departmentA = Department::factory()->create(['code' => 'ACC', 'name' => 'Accounting']);
    $departmentB = Department::factory()->create(['code' => 'REC', 'name' => 'Records']);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $departmentA->id]);
    $processor = User::factory()->create(['department_id' => $departmentA->id]);
    $sender = User::factory()->create(['department_id' => $departmentB->id]);

    $acceptedDocument = Document::factory()->create([
        'current_department_id' => $departmentA->id,
        'status' => DocumentWorkflowStatus::Finished,
    ]);
    DocumentTransfer::factory()->create([
        'document_id' => $acceptedDocument->id,
        'from_department_id' => $departmentB->id,
        'to_department_id' => $departmentA->id,
        'forwarded_by_user_id' => $sender->id,
        'accepted_by_user_id' => $processor->id,
        'status' => TransferStatus::Accepted,
        'forwarded_at' => Carbon::create(2026, 2, 5, 8, 0, 0),
        'accepted_at' => Carbon::create(2026, 2, 6, 8, 0, 0),
    ]);

    $incomingPendingDocument = Document::factory()->create([
        'current_department_id' => $departmentA->id,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);
    DocumentTransfer::factory()->create([
        'document_id' => $incomingPendingDocument->id,
        'from_department_id' => $departmentB->id,
        'to_department_id' => $departmentA->id,
        'forwarded_by_user_id' => $sender->id,
        'status' => TransferStatus::Pending,
        'forwarded_at' => Carbon::create(2026, 2, 10, 8, 0, 0),
        'accepted_at' => null,
    ]);

    $processedDocument = Document::factory()->create([
        'current_department_id' => $departmentB->id,
        'status' => DocumentWorkflowStatus::Outgoing,
    ]);
    DocumentTransfer::factory()->create([
        'document_id' => $processedDocument->id,
        'from_department_id' => $departmentA->id,
        'to_department_id' => $departmentB->id,
        'forwarded_by_user_id' => $processor->id,
        'status' => TransferStatus::Accepted,
        'forwarded_at' => Carbon::create(2026, 2, 12, 10, 0, 0),
        'accepted_at' => Carbon::create(2026, 2, 12, 15, 0, 0),
    ]);

    Document::factory()->create([
        'current_department_id' => $departmentA->id,
        'current_user_id' => $processor->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'updated_at' => Carbon::create(2026, 2, 1, 9, 0, 0),
    ]);

    $response = $this->actingAs($manager)->get(route('reports.departments.monthly', [
        'department_id' => $departmentA->id,
        'month' => '2026-02',
    ]));

    Carbon::setTestNow();

    $response->assertSuccessful();
    $response->assertSee('Department Monthly Reports');
    $response->assertSee('Received');
    $response->assertSee('2');
    $response->assertSee('Processed');
    $response->assertSee('1');
    $response->assertSee('Pending Total');
    $response->assertSee('2');
    $response->assertSee('Average Hours');
    $response->assertSee('24');
});

test('monthly report export returns csv download', function () {
    $department = Department::factory()->create(['code' => 'BUD']);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);

    $response = $this->actingAs($manager)->get(route('reports.departments.monthly.export', [
        'department_id' => $department->id,
        'month' => '2026-02',
    ]));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition');
    expect($response->getContent())->toContain('Department Monthly Report');
    expect($response->getContent())->toContain($department->name);
});

test('monthly report generation job stores csv files for active departments', function () {
    Storage::fake('local');

    $activeDepartment = Department::factory()->create(['code' => 'ACT-001', 'is_active' => true]);
    Department::factory()->create(['code' => 'INA-001', 'is_active' => false]);

    $job = new GenerateDepartmentMonthlyReportsJob('2026-02');
    $job->handle(app(\App\Services\DepartmentMonthlyReportService::class));

    Storage::disk('local')->assertExists('reports/monthly/2026-02/act-001.csv');
    Storage::disk('local')->assertMissing('reports/monthly/2026-02/ina-001.csv');
});
