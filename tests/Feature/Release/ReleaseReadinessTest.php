<?php

use App\DocumentAlertType;
use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAlert;
use App\Models\DocumentCase;
use App\Models\DocumentEvent;
use App\Models\DocumentItem;
use App\Models\User;
use App\Services\DepartmentMonthlyReportService;
use App\Services\DocumentAlertService;
use App\Services\DocumentCustodyService;
use App\Services\DocumentRelationshipService;
use App\Services\DocumentWorkflowService;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('route smoke access matrix matches role and middleware expectations', function () {
    $regularUser = User::factory()->create(['role' => UserRole::Regular]);
    $managerUser = User::factory()->create(['role' => UserRole::Manager]);

    $this->get('/')->assertSuccessful();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->get(route('documents.queues.index'))->assertRedirect(route('login'));
    $this->get(route('reports.departments.monthly'))->assertRedirect(route('login'));

    $this->actingAs($regularUser)->get(route('dashboard'))->assertSuccessful();
    $this->actingAs($regularUser)->get(route('profile.edit'))->assertSuccessful();
    $this->actingAs($regularUser)->get(route('documents.queues.index'))->assertSuccessful();
    $this->actingAs($regularUser)->get(route('reports.departments.monthly'))->assertForbidden();

    $this->actingAs($managerUser)->get(route('reports.departments.monthly'))->assertSuccessful();
});

test('database seeding creates required baseline departments and admin account', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::query()->count())->toBeGreaterThanOrEqual(5);
    expect(Department::query()->where('code', 'RECORDS')->exists())->toBeTrue();
    expect(Department::query()->where('code', 'ACCOUNTING')->exists())->toBeTrue();

    $admin = User::query()->where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin?->role)->toBe(UserRole::Admin);
    expect($admin?->department?->code)->toBe('RECORDS');
});

test('end to end happy path covers phases one to nine core flow', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 20, 9, 0, 0));
    $this->seed(DepartmentSeeder::class);

    $records = Department::query()->where('code', 'RECORDS')->firstOrFail();
    $accounting = Department::query()->where('code', 'ACCOUNTING')->firstOrFail();
    $budget = Department::query()->where('code', 'BUDGET')->firstOrFail();

    $recordsUser = User::factory()->create(['role' => UserRole::Regular, 'department_id' => $records->id]);
    $accountingUser = User::factory()->create(['role' => UserRole::Regular, 'department_id' => $accounting->id]);
    $budgetUser = User::factory()->create(['role' => UserRole::Regular, 'department_id' => $budget->id]);

    $documentCase = DocumentCase::factory()->create([
        'owner_type' => 'school',
        'owner_name' => 'Sample School',
        'status' => 'open',
    ]);

    $mainDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $records->id,
        'current_user_id' => $recordsUser->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 15, 17, 0, 0),
        'is_returnable' => true,
    ]);

    DocumentItem::factory()->create([
        'document_id' => $mainDocument->id,
        'item_type' => 'main',
        'name' => 'Salary Claim Main Form',
    ]);
    DocumentItem::factory()->create([
        'document_id' => $mainDocument->id,
        'item_type' => 'attachment',
        'name' => 'Supporting Attachment',
    ]);

    $workflowService = app(DocumentWorkflowService::class);
    $custodyService = app(DocumentCustodyService::class);
    $relationshipService = app(DocumentRelationshipService::class);
    $reportService = app(DepartmentMonthlyReportService::class);
    $alertService = app(DocumentAlertService::class);

    $workflowService->forward($mainDocument, $recordsUser, $accounting, 'Forwarded to Accounting');
    $mainDocument->refresh();
    expect($mainDocument->status)->toBe(DocumentWorkflowStatus::Outgoing);

    $workflowService->accept($mainDocument, $accountingUser);
    $mainDocument->refresh();
    expect($mainDocument->status)->toBe(DocumentWorkflowStatus::OnQueue);
    expect($mainDocument->current_department_id)->toBe($accounting->id);

    $custodyService->assignOriginalCustody(
        document: $mainDocument,
        department: $accounting,
        custodian: $accountingUser,
        physicalLocation: 'Cabinet A / Drawer 1',
        storageReference: 'ACC-001'
    );
    $mainDocument->refresh();
    expect($mainDocument->original_current_department_id)->toBe($accounting->id);

    $supportDocument = Document::factory()->create([
        'document_case_id' => $documentCase->id,
        'current_department_id' => $accounting->id,
        'current_user_id' => $accountingUser->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $relationshipService->attachTo($mainDocument, [$supportDocument], $accountingUser, 'Attached supporting document');

    $workflowService->forward($mainDocument, $accountingUser, $budget, 'Forwarded to Budget');
    $workflowService->accept($mainDocument, $budgetUser);
    $mainDocument->refresh();

    DB::table('documents')->where('id', $mainDocument->id)->update([
        'updated_at' => Carbon::create(2026, 2, 10, 8, 0, 0),
    ]);

    $accountingReport = $reportService->buildReport($accounting, Carbon::create(2026, 2, 1));
    $alertResult = $alertService->generateAlerts(Carbon::create(2026, 2, 20, 12, 0, 0));

    Carbon::setTestNow();

    expect($accountingReport['metrics']['received_count'])->toBeGreaterThanOrEqual(1);
    expect($accountingReport['metrics']['processed_count'])->toBeGreaterThanOrEqual(1);

    expect(DocumentEvent::query()
        ->where('document_id', $mainDocument->id)
        ->whereIn('event_type', [
            DocumentEventType::WorkflowForwarded->value,
            DocumentEventType::WorkflowAccepted->value,
            DocumentEventType::CustodyAssigned->value,
            DocumentEventType::RemarkAdded->value,
        ])
        ->count())->toBeGreaterThanOrEqual(5);
    expect(DocumentEvent::query()
        ->where('document_id', $supportDocument->id)
        ->where('event_type', DocumentEventType::RelationshipLinked->value)
        ->exists())->toBeTrue();

    expect($alertResult['created'])->toBeGreaterThanOrEqual(1);
    expect(DocumentAlert::query()
        ->where('document_id', $mainDocument->id)
        ->where('alert_type', DocumentAlertType::Overdue->value)
        ->where('is_active', true)
        ->exists())->toBeTrue();
    expect(DocumentAlert::query()
        ->where('document_id', $mainDocument->id)
        ->where('alert_type', DocumentAlertType::Stalled->value)
        ->where('is_active', true)
        ->exists())->toBeTrue();
});
