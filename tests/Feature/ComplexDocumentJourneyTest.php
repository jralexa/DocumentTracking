<?php

use App\DocumentAlertType;
use App\DocumentVersionType;
use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAlert;
use App\Models\DocumentCopy;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\DocumentAlertService;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Support\Carbon;

test('complex document journey runs from intake to completion and alert generation', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 20, 8, 30, 0, 'Asia/Manila'));

    try {
        $records = Department::factory()->create(['code' => 'RECORDS', 'name' => 'Records Section', 'is_active' => true]);
        $hrmo = Department::factory()->create(['code' => 'HRMO', 'name' => 'Human Resource Management Office', 'is_active' => true]);
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
        $payrollUser = User::factory()->create([
            'name' => 'Payroll Processor',
            'email' => 'payroll.processor@example.com',
            'role' => UserRole::Regular,
            'department_id' => $payroll->id,
        ]);

        $this->actingAs($recordsUser)->post(route('documents.store'), [
            'case_mode' => 'new',
            'case_title' => 'Personnel Actions - Ana Reyes',
            'subject' => 'Master Personnel 201 File - Ana Reyes',
            'reference_number' => 'HR-2026-031',
            'document_type' => 'for_processing',
            'owner_type' => 'personal',
            'owner_name' => 'Ana Reyes',
        ])->assertRedirect(route('documents.create'));

        $document = Document::query()
            ->where('subject', 'Master Personnel 201 File - Ana Reyes')
            ->firstOrFail();

        expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
        expect($document->current_department_id)->toBe($records->id);
        expect($document->current_user_id)->toBeNull();

        $this->actingAs($recordsUser)->post(route('documents.accept', $document))->assertRedirect();
        $document->refresh();
        expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($document->current_user_id)->toBe($recordsUser->id);

        $this->actingAs($recordsUser)->from(route('documents.queues.index'))
            ->post(route('documents.forward', $document), [
                'to_department_id' => $hrmo->id,
                'remarks' => 'Forwarding original for HRMO processing.',
                'forward_version_type' => DocumentVersionType::Original->value,
                'copy_kept' => '1',
                'copy_storage_location' => 'Records Cabinet A1',
                'copy_purpose' => 'Records reference copy',
            ])->assertRedirect(route('documents.queues.index'));

        $document->refresh();
        expect($document->status)->toBe(DocumentWorkflowStatus::Outgoing);
        expect($document->current_department_id)->toBe($hrmo->id);
        expect($document->current_user_id)->toBeNull();
        expect($document->transfers()->latest('id')->value('status'))->toBe(TransferStatus::Pending);

        $this->actingAs($hrmoUser)->post(route('documents.accept', $document))->assertRedirect();
        $document->refresh();
        expect($document->status)->toBe(DocumentWorkflowStatus::OnQueue);
        expect($document->current_user_id)->toBe($hrmoUser->id);

        $this->actingAs($hrmoUser)->post(route('documents.complete', $document), [
            'remarks' => 'HRMO processing completed and filed.',
        ])->assertRedirect();

        $document->refresh();
        expect($document->status)->toBe(DocumentWorkflowStatus::Finished);

        expect(DocumentCopy::query()
            ->where('document_id', $document->id)
            ->where('storage_location', 'Records Cabinet A1')
            ->exists())->toBeTrue();

        $stalledOverdueDocument = Document::factory()->create([
            'document_case_id' => $document->document_case_id,
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
            ->whereIn('action', ['document_forwarded', 'document_accepted'])
            ->count())->toBeGreaterThan(0);
    } finally {
        Carbon::setTestNow();
    }
});
