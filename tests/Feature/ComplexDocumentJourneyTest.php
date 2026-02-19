<?php

use App\DocumentAlertType;
use App\DocumentRelationshipType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAlert;
use App\Models\DocumentCopy;
use App\Models\DocumentRelationship;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\DocumentAlertService;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Support\Carbon;

test('complex document journey runs from intake to split workflow completion and alert generation', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 20, 8, 30, 0, 'Asia/Manila'));

    try {
        $records = Department::factory()->create(['code' => 'RECORDS', 'name' => 'Records Section', 'is_active' => true]);
        $hrmo = Department::factory()->create(['code' => 'HRMO', 'name' => 'Human Resource Management Office', 'is_active' => true]);
        $accounting = Department::factory()->create(['code' => 'ACCOUNTING', 'name' => 'Accounting Office', 'is_active' => true]);
        $payroll = Department::factory()->create(['code' => 'PAYROLL', 'name' => 'Payroll Services', 'is_active' => true]);

        $recordsUser = User::factory()->create([
            'name' => 'Records Processor',
            'email' => 'records.processor@example.com',
            'role' => UserRole::Regular,
            'department_id' => $records->id,
        ]);
        $hrmoUser = User::factory()->create([
            'name' => 'HRMO Processor',
            'email' => 'hrmo.processor@example.com',
            'role' => UserRole::Regular,
            'department_id' => $hrmo->id,
        ]);
        $accountingUser = User::factory()->create([
            'name' => 'Accounting Processor',
            'email' => 'accounting.processor@example.com',
            'role' => UserRole::Regular,
            'department_id' => $accounting->id,
        ]);
        $payrollUser = User::factory()->create([
            'name' => 'Payroll Processor',
            'email' => 'payroll.processor@example.com',
            'role' => UserRole::Regular,
            'department_id' => $payroll->id,
        ]);
        $managerUser = User::factory()->create([
            'name' => 'Division Manager',
            'email' => 'division.manager@example.com',
            'role' => UserRole::Manager,
            'department_id' => $records->id,
        ]);
        $hrmoManagerUser = User::factory()->create([
            'name' => 'HRMO Manager',
            'email' => 'hrmo.manager@example.com',
            'role' => UserRole::Manager,
            'department_id' => $hrmo->id,
        ]);

        $this->actingAs($recordsUser)->post(route('documents.store'), [
            'quick_mode' => '1',
            'case_mode' => 'new',
            'case_title' => 'Personnel Actions - Ana Reyes',
            'subject' => 'Master Personnel 201 File - Ana Reyes',
            'reference_number' => 'HR-2026-031',
            'document_type' => 'for_processing',
            'owner_type' => 'personal',
            'owner_name' => 'Ana Reyes',
            'initial_remarks' => 'Initial intake package.',
            'is_returnable' => '1',
            'return_deadline' => now()->addDays(30)->toDateString(),
        ])->assertRedirect(route('documents.queues.index'));

        $parent = Document::query()
            ->where('subject', 'Master Personnel 201 File - Ana Reyes')
            ->firstOrFail();

        expect($parent->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($parent->current_department_id)->toBe($records->id);
        expect($parent->current_user_id)->toBe($recordsUser->id);
        expect($parent->document_case_id)->not->toBeNull();

        $this->actingAs($recordsUser)->post(route('documents.split.store', $parent), [
            'confirm_routing_reviewed' => '1',
            'children' => [
                [
                    'routing_mode' => 'child',
                    'subject' => 'Original 201 File - HRMO Processing',
                    'document_type' => 'for_processing',
                    'owner_type' => 'personal',
                    'owner_name' => 'Ana Reyes',
                    'to_department_ids' => [$hrmo->id],
                    'forward_version_type' => DocumentVersionType::Original->value,
                    'is_returnable' => '1',
                    'return_deadline' => now()->addDays(15)->toDateString(),
                    'remarks' => 'Original set for HRMO custody and processing.',
                ],
                [
                    'routing_mode' => 'child',
                    'subject' => 'Payroll and Accounting Evaluation Packet',
                    'document_type' => 'for_processing',
                    'owner_type' => 'personal',
                    'owner_name' => 'Ana Reyes',
                    'to_department_ids' => [$accounting->id, $payroll->id],
                    'forward_version_type' => DocumentVersionType::Photocopy->value,
                    'original_storage_location' => 'Records Vault Shelf A1',
                    'copy_kept' => '1',
                    'copy_storage_location' => 'Records Cabinet C2',
                    'copy_purpose' => 'Records reference copy',
                    'remarks' => 'Photocopy routing for parallel validation.',
                ],
            ],
        ])->assertRedirect(route('cases.show', $parent->document_case_id));

        $parent->refresh();
        expect(data_get($parent->metadata, 'split_completed'))->toBeTrue();
        expect(data_get($parent->metadata, 'split_children_count'))->toBe(3);

        $children = Document::query()
            ->where('document_case_id', $parent->document_case_id)
            ->where('id', '!=', $parent->id)
            ->orderBy('id')
            ->get();

        expect($children)->toHaveCount(3);
        expect($children->every(fn (Document $document): bool => $document->status === DocumentWorkflowStatus::Outgoing))->toBeTrue();

        expect(DocumentRelationship::query()
            ->where('relation_type', DocumentRelationshipType::SplitFrom->value)
            ->where('related_document_id', $parent->id)
            ->count())->toBe(3);

        $hrmoChild = $children
            ->first(fn (Document $document): bool => $document->subject === 'Original 201 File - HRMO Processing');
        $accountingChild = $children
            ->first(fn (Document $document): bool => $document->subject === 'Payroll and Accounting Evaluation Packet' && $document->current_department_id === $accounting->id);
        $payrollChild = $children
            ->first(fn (Document $document): bool => $document->subject === 'Payroll and Accounting Evaluation Packet' && $document->current_department_id === $payroll->id);

        expect($hrmoChild)->not->toBeNull();
        expect($accountingChild)->not->toBeNull();
        expect($payrollChild)->not->toBeNull();

        $this->actingAs($hrmoUser)->post(route('documents.accept', $hrmoChild))->assertRedirect();
        $this->actingAs($accountingUser)->post(route('documents.accept', $accountingChild))->assertRedirect();
        $this->actingAs($payrollUser)->post(route('documents.accept', $payrollChild))->assertRedirect();

        $hrmoChild->refresh();
        $accountingChild->refresh();
        $payrollChild->refresh();

        expect($hrmoChild->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($hrmoChild->current_user_id)->toBe($hrmoUser->id);
        expect($accountingChild->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($accountingChild->current_user_id)->toBe($accountingUser->id);
        expect($payrollChild->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($payrollChild->current_user_id)->toBe($payrollUser->id);

        $this->actingAs($accountingUser)->from(route('documents.queues.index'))
            ->post(route('documents.forward', $accountingChild), [
                'to_department_id' => $records->id,
                'remarks' => 'Forwarding certified copy after accounting review.',
                'forward_version_type' => DocumentVersionType::CertifiedCopy->value,
                'copy_kept' => '1',
                'copy_storage_location' => 'Accounting Cabinet 3',
                'copy_purpose' => 'Accounting audit reference',
            ])->assertRedirect(route('documents.queues.index'));

        $accountingChild->refresh();
        expect($accountingChild->status)->toBe(DocumentWorkflowStatus::Outgoing);
        expect($accountingChild->current_department_id)->toBe($records->id);
        expect($accountingChild->current_user_id)->toBeNull();
        expect($accountingChild->transfers()->latest('id')->value('status'))->toBe(TransferStatus::Pending);

        $this->actingAs($recordsUser)->post(route('documents.accept', $accountingChild))->assertRedirect();
        $accountingChild->refresh();
        expect($accountingChild->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($accountingChild->current_user_id)->toBe($recordsUser->id);

        $this->actingAs($recordsUser)->post(route('documents.complete', $accountingChild), [
            'remarks' => 'Accounting validation complete and filed.',
        ])->assertRedirect();

        $this->actingAs($payrollUser)->post(route('documents.complete', $payrollChild), [
            'remarks' => 'Payroll evaluation completed.',
        ])->assertRedirect();

        $accountingChild->refresh();
        $payrollChild->refresh();
        expect($accountingChild->status)->toBe(DocumentWorkflowStatus::Finished);
        expect($accountingChild->completed_at)->not->toBeNull();
        expect($payrollChild->status)->toBe(DocumentWorkflowStatus::Finished);
        expect($payrollChild->completed_at)->not->toBeNull();

        $this->actingAs($hrmoManagerUser)->post(route('custody.returnables.returned', $hrmoChild), [
            'returned_to' => 'Ana Reyes',
            'returned_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $hrmoChild->refresh();
        expect($hrmoChild->returned_to)->toBe('Ana Reyes');
        expect($hrmoChild->returned_at)->not->toBeNull();
        expect($hrmoChild->status)->toBe(DocumentWorkflowStatus::Finished);

        expect(DocumentCopy::query()
            ->where('document_id', $accountingChild->id)
            ->where('storage_location', 'Accounting Cabinet 3')
            ->exists())->toBeTrue();

        $stalledOverdueDocument = Document::factory()->create([
            'document_case_id' => $parent->document_case_id,
            'current_department_id' => $payroll->id,
            'current_user_id' => $payrollUser->id,
            'status' => DocumentWorkflowStatus::OnQueue,
            'due_at' => now()->subDay(),
            'received_at' => now()->subDays(6),
            'updated_at' => now()->subDays(4),
        ]);

        $alertService = app(DocumentAlertService::class);
        $alertResult = $alertService->generateAlerts(now());
        $dashboardData = $alertService->getDashboardData($payrollUser);

        expect($alertResult['created'])->toBeGreaterThanOrEqual(2);
        expect(DocumentAlert::query()
            ->where('document_id', $stalledOverdueDocument->id)
            ->where('alert_type', DocumentAlertType::Overdue->value)
            ->where('is_active', true)
            ->exists())->toBeTrue();
        expect(DocumentAlert::query()
            ->where('document_id', $stalledOverdueDocument->id)
            ->where('alert_type', DocumentAlertType::Stalled->value)
            ->where('is_active', true)
            ->exists())->toBeTrue();
        expect($dashboardData['counts']['overdue'])->toBeGreaterThanOrEqual(1);
        expect($dashboardData['counts']['stalled'])->toBeGreaterThanOrEqual(1);

        expect(SystemLog::query()
            ->whereIn('action', ['document_forwarded', 'document_accepted', 'document_completed'])
            ->count())->toBeGreaterThan(0);
    } finally {
        Carbon::setTestNow();
    }
});
