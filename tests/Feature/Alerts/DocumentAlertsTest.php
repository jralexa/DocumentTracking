<?php

use App\DocumentAlertType;
use App\DocumentWorkflowStatus;
use App\Jobs\GenerateDocumentAlertsJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAlert;
use App\Models\User;
use App\Services\DocumentAlertService;
use App\UserRole;
use Illuminate\Support\Carbon;

test('generates overdue and stalled alerts', function () {
    $service = app(DocumentAlertService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);

    Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
        'updated_at' => Carbon::create(2026, 2, 5, 8, 0, 0),
    ]);

    Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
        'updated_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
    ]);

    $result = $service->generateAlerts(Carbon::create(2026, 2, 10, 9, 0, 0));

    expect($result['created'])->toBe(3);
    expect($result['resolved'])->toBe(0);
    expect(DocumentAlert::query()->where('alert_type', DocumentAlertType::Overdue->value)->where('is_active', true)->count())->toBe(1);
    expect(DocumentAlert::query()->where('alert_type', DocumentAlertType::Stalled->value)->where('is_active', true)->count())->toBe(2);
});

test('alert generation is idempotent for active conditions', function () {
    $service = app(DocumentAlertService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
        'updated_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
    ]);

    $service->generateAlerts(Carbon::create(2026, 2, 10, 9, 0, 0));
    $result = $service->generateAlerts(Carbon::create(2026, 2, 10, 10, 0, 0));

    expect($result['created'])->toBe(0);
    expect(DocumentAlert::query()->where('document_id', $document->id)->where('is_active', true)->count())->toBe(2);
});

test('resolved alerts are closed when conditions clear', function () {
    $service = app(DocumentAlertService::class);
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $document = Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
        'updated_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
    ]);

    $service->generateAlerts(Carbon::create(2026, 2, 10, 9, 0, 0));

    $document->forceFill([
        'status' => DocumentWorkflowStatus::Finished,
        'updated_at' => Carbon::create(2026, 2, 10, 9, 30, 0),
    ])->save();

    $result = $service->generateAlerts(Carbon::create(2026, 2, 10, 10, 0, 0));

    expect($result['resolved'])->toBe(2);
    expect(DocumentAlert::query()->where('document_id', $document->id)->where('is_active', true)->count())->toBe(0);
    expect(DocumentAlert::query()->where('document_id', $document->id)->whereNotNull('resolved_at')->count())->toBe(2);
});

test('dashboard shows only active department alerts for processing users', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $departmentA->id]);

    $documentA = Document::factory()->create(['current_department_id' => $departmentA->id]);
    $documentB = Document::factory()->create(['current_department_id' => $departmentB->id]);

    DocumentAlert::factory()->create([
        'document_id' => $documentA->id,
        'department_id' => $departmentA->id,
        'alert_type' => DocumentAlertType::Overdue,
        'message' => 'Dept A alert',
        'is_active' => true,
    ]);

    DocumentAlert::factory()->create([
        'document_id' => $documentB->id,
        'department_id' => $departmentB->id,
        'alert_type' => DocumentAlertType::Overdue,
        'message' => 'Dept B alert',
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Dept A alert');
    $response->assertDontSee('Dept B alert');
    $response->assertViewHas('alertCounts', function (array $counts): bool {
        return $counts['total_active'] === 1 && $counts['overdue'] === 1;
    });
});

test('dashboard shows zero alerts for users without process permission', function () {
    $department = Department::factory()->create();
    $guestUser = User::factory()->create(['role' => UserRole::Guest, 'department_id' => $department->id]);
    $document = Document::factory()->create(['current_department_id' => $department->id]);

    DocumentAlert::factory()->create([
        'document_id' => $document->id,
        'department_id' => $department->id,
        'alert_type' => DocumentAlertType::Overdue,
        'is_active' => true,
    ]);

    $response = $this->actingAs($guestUser)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertViewHas('alertCounts', function (array $counts): bool {
        return $counts['total_active'] === 0 && $counts['overdue'] === 0 && $counts['stalled'] === 0;
    });
});

test('generate document alerts job triggers alert creation', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);

    Document::factory()->create([
        'current_department_id' => $department->id,
        'current_user_id' => $user->id,
        'status' => DocumentWorkflowStatus::OnQueue,
        'due_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
        'updated_at' => Carbon::create(2026, 2, 1, 8, 0, 0),
    ]);

    Carbon::setTestNow(Carbon::create(2026, 2, 10, 9, 0, 0));

    $job = new GenerateDocumentAlertsJob;
    $job->handle(app(DocumentAlertService::class));

    Carbon::setTestNow();

    expect(DocumentAlert::query()->where('is_active', true)->count())->toBe(2);
});
