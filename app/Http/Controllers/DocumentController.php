<?php

namespace App\Http\Controllers;

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Department;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\School;
use App\Models\User;
use App\Services\DocumentAuditService;
use App\TransferStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentAuditService $auditService) {}

    /**
     * Show the document creation form.
     */
    public function create(): View
    {
        $openCases = DocumentCase::query()
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->limit(50)
            ->get(['id', 'case_number', 'title']);

        $districts = District::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'district_id', 'name']);

        return view('documents.create', [
            'openCases' => $openCases,
            'districts' => $districts,
            'schools' => $schools,
        ]);
    }

    /**
     * Store a newly created document and assign it to the current user queue.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        $validated = $request->validated();
        $ownerPayload = $this->resolveOwnerPayload($validated);
        $intakeDepartmentId = $this->resolveIntakeDepartmentId($user->department_id);

        abort_if($intakeDepartmentId === null, 422, 'No active intake department is configured.');
        $intakeDepartmentName = $this->resolveIntakeDepartmentName($intakeDepartmentId);
        $trackingNumber = $this->createDocumentWithRelatedRecords(
            validated: $validated,
            ownerPayload: $ownerPayload,
            user: $user,
            intakeDepartmentId: $intakeDepartmentId
        );

        if ($user->canProcessDocuments()) {
            return redirect()
                ->route('documents.queues.index')
                ->with('status', 'Document recorded and added to your action queue.');
        }

        return redirect()
            ->route('documents.create')
            ->with('status', 'Document recorded successfully. Tracking number: '.$trackingNumber)
            ->with('intake_notice', 'Submitted to '.$intakeDepartmentName.' incoming queue.');
    }

    /**
     * Generate a unique daily tracking number in YYMMDD### format.
     */
    protected function generateTrackingNumber(Carbon $dateTime): string
    {
        $prefix = $dateTime->format('ymd');

        $lastTrackingNumber = Document::query()
            ->where('tracking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $nextSequence = $lastTrackingNumber === null
            ? 1
            : ((int) substr($lastTrackingNumber, 6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a case number in CASE-YYYYMMDD-### format.
     */
    protected function generateCaseNumber(Carbon $dateTime): string
    {
        $prefix = 'CASE-'.$dateTime->format('Ymd').'-';

        $lastCaseNumber = DocumentCase::query()
            ->where('case_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('case_number')
            ->value('case_number');

        $nextSequence = $lastCaseNumber === null
            ? 1
            : ((int) substr($lastCaseNumber, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve and validate owner payload against district/school masters.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *   owner_district_id:int|null,
     *   owner_school_id:int|null,
     *   owner_name:string,
     *   owner_reference:string|null,
     *   owner_district_name:string|null,
     *   owner_school_name:string|null
     * }
     */
    protected function resolveOwnerPayload(array $validated): array
    {
        $ownerType = (string) $validated['owner_type'];
        $ownerDistrictId = isset($validated['owner_district_id']) ? (int) $validated['owner_district_id'] : null;
        $ownerSchoolId = isset($validated['owner_school_id']) ? (int) $validated['owner_school_id'] : null;
        $ownerReference = $this->resolveOwnerReference($validated);

        return match ($ownerType) {
            'district' => $this->resolveDistrictOwnerPayload($validated, $ownerDistrictId, $ownerReference),
            'school' => $this->resolveSchoolOwnerPayload($validated, $ownerDistrictId, $ownerSchoolId, $ownerReference),
            default => $this->resolveManualOwnerPayload((string) $validated['owner_name'], $ownerReference),
        };
    }

    /**
     * Resolve the current authenticated user.
     */
    protected function resolveAuthenticatedUser(StoreDocumentRequest $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Resolve intake department id for new document intake.
     */
    protected function resolveIntakeDepartmentId(?int $userDepartmentId): ?int
    {
        return $userDepartmentId
            ?? Department::query()->where('code', 'RECORDS')->value('id')
            ?? Department::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    /**
     * Resolve intake department display name.
     */
    protected function resolveIntakeDepartmentName(int $intakeDepartmentId): string
    {
        return Department::query()->whereKey($intakeDepartmentId)->value('name') ?? 'Records Section';
    }

    /**
     * Create document case, document, and initial audit trail records.
     *
     * @param  array<string, mixed>  $validated
     * @param  array{
     *   owner_district_id:int|null,
     *   owner_school_id:int|null,
     *   owner_name:string,
     *   owner_reference:string|null,
     *   owner_district_name:string|null,
     *   owner_school_name:string|null
     * }  $ownerPayload
     */
    protected function createDocumentWithRelatedRecords(array $validated, array $ownerPayload, User $user, int $intakeDepartmentId): string
    {
        return DB::transaction(function () use ($validated, $ownerPayload, $user, $intakeDepartmentId): string {
            $now = now();
            $priority = $validated['priority'] ?? 'normal';
            $canProcessDocuments = $user->canProcessDocuments();
            $documentCase = $this->resolveDocumentCase(
                validated: $validated,
                ownerPayload: $ownerPayload,
                now: $now,
                priority: $priority
            );
            $trackingNumber = $this->generateTrackingNumber($now);

            $document = Document::query()->create([
                'document_case_id' => $documentCase->id,
                'current_department_id' => $intakeDepartmentId,
                'current_user_id' => $canProcessDocuments ? $user->id : null,
                'tracking_number' => $trackingNumber,
                'reference_number' => $validated['reference_number'] ?? null,
                'subject' => $validated['subject'],
                'document_type' => $validated['document_type'],
                'owner_type' => $validated['owner_type'],
                'owner_district_id' => $ownerPayload['owner_district_id'],
                'owner_school_id' => $ownerPayload['owner_school_id'],
                'owner_name' => $ownerPayload['owner_name'],
                'status' => $canProcessDocuments ? DocumentWorkflowStatus::OnQueue : DocumentWorkflowStatus::Outgoing,
                'priority' => $priority,
                'received_at' => $now,
                'due_at' => $validated['due_at'] ?? null,
                'metadata' => [
                    'created_from' => 'web_form',
                    'owner_reference' => $ownerPayload['owner_reference'],
                    'owner_district_name' => $ownerPayload['owner_district_name'],
                    'owner_school_name' => $ownerPayload['owner_school_name'],
                ],
                'is_returnable' => (bool) ($validated['is_returnable'] ?? false),
                'return_deadline' => $validated['return_deadline'] ?? null,
            ]);

            $document->items()->create([
                'name' => $validated['item_name'] ?? $validated['subject'],
                'item_type' => 'main',
                'status' => 'active',
                'quantity' => 1,
                'sort_order' => 0,
            ]);

            $this->auditService->recordEvent(
                document: $document,
                eventType: DocumentEventType::DocumentCreated,
                actedBy: $user,
                message: 'Document created through add document form.',
                context: 'creation',
                payload: [
                    'case_id' => $documentCase->id,
                    'tracking_number' => $trackingNumber,
                ]
            );

            if (! empty($validated['initial_remarks'])) {
                $this->auditService->addRemark(
                    document: $document,
                    remark: $validated['initial_remarks'],
                    user: $user,
                    context: 'creation'
                );
            }

            if (! $canProcessDocuments) {
                $transfer = $document->transfers()->create([
                    'from_department_id' => null,
                    'to_department_id' => $intakeDepartmentId,
                    'forwarded_by_user_id' => $user->id,
                    'status' => TransferStatus::Pending,
                    'remarks' => 'Submitted through intake form.',
                    'forwarded_at' => $now,
                ]);

                $this->auditService->recordEvent(
                    document: $document,
                    eventType: DocumentEventType::WorkflowForwarded,
                    actedBy: $user,
                    message: 'Document submitted to intake department incoming queue.',
                    context: 'creation',
                    transfer: $transfer
                );
            }

            return $trackingNumber;
        });
    }

    /**
     * Resolve the target case for the new document.
     *
     * @param  array<string, mixed>  $validated
     * @param  array{
     *   owner_name:string,
     *   owner_reference:string|null
     * }  $ownerPayload
     */
    protected function resolveDocumentCase(array $validated, array $ownerPayload, Carbon $now, string $priority): DocumentCase
    {
        if (($validated['case_mode'] ?? 'new') === 'existing') {
            return DocumentCase::query()->lockForUpdate()->findOrFail((int) $validated['document_case_id']);
        }

        return DocumentCase::query()->create([
            'case_number' => $this->generateCaseNumber($now),
            'title' => $validated['case_title'] ?? $validated['subject'],
            'owner_type' => $validated['owner_type'],
            'owner_name' => $ownerPayload['owner_name'],
            'owner_reference' => $ownerPayload['owner_reference'],
            'description' => $validated['description'] ?? null,
            'status' => 'open',
            'priority' => $priority,
            'opened_at' => $now,
        ]);
    }

    /**
     * Resolve optional owner reference.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveOwnerReference(array $validated): ?string
    {
        return isset($validated['owner_reference']) && $validated['owner_reference'] !== ''
            ? (string) $validated['owner_reference']
            : null;
    }

    /**
     * Resolve owner payload for district owner type.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *   owner_district_id:int|null,
     *   owner_school_id:int|null,
     *   owner_name:string,
     *   owner_reference:string|null,
     *   owner_district_name:string|null,
     *   owner_school_name:string|null
     * }
     */
    protected function resolveDistrictOwnerPayload(array $validated, ?int $ownerDistrictId, ?string $ownerReference): array
    {
        if ($ownerDistrictId === null) {
            return $this->resolveManualOwnerPayload((string) ($validated['owner_name'] ?? ''), $ownerReference);
        }

        $district = District::query()
            ->where('is_active', true)
            ->findOrFail($ownerDistrictId);

        return [
            'owner_district_id' => $district->id,
            'owner_school_id' => null,
            'owner_name' => $district->name,
            'owner_reference' => $ownerReference ?? $district->code,
            'owner_district_name' => $district->name,
            'owner_school_name' => null,
        ];
    }

    /**
     * Resolve owner payload for school owner type.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *   owner_district_id:int|null,
     *   owner_school_id:int|null,
     *   owner_name:string,
     *   owner_reference:string|null,
     *   owner_district_name:string|null,
     *   owner_school_name:string|null
     * }
     */
    protected function resolveSchoolOwnerPayload(array $validated, ?int $ownerDistrictId, ?int $ownerSchoolId, ?string $ownerReference): array
    {
        if ($ownerSchoolId === null) {
            return $this->resolveManualOwnerPayload((string) ($validated['owner_name'] ?? ''), $ownerReference);
        }

        $school = School::query()
            ->with('district:id,name')
            ->where('is_active', true)
            ->findOrFail($ownerSchoolId);

        if ($ownerDistrictId !== null && $school->district_id !== $ownerDistrictId) {
            abort(422, 'Selected school does not belong to the selected district.');
        }

        return [
            'owner_district_id' => $school->district_id,
            'owner_school_id' => $school->id,
            'owner_name' => $school->name,
            'owner_reference' => $ownerReference ?? $school->code,
            'owner_district_name' => $school->district?->name,
            'owner_school_name' => $school->name,
        ];
    }

    /**
     * Resolve owner payload for manual owner input.
     *
     * @return array{
     *   owner_district_id:int|null,
     *   owner_school_id:int|null,
     *   owner_name:string,
     *   owner_reference:string|null,
     *   owner_district_name:string|null,
     *   owner_school_name:string|null
     * }
     */
    protected function resolveManualOwnerPayload(string $ownerName, ?string $ownerReference): array
    {
        return [
            'owner_district_id' => null,
            'owner_school_id' => null,
            'owner_name' => $ownerName,
            'owner_reference' => $ownerReference,
            'owner_district_name' => null,
            'owner_school_name' => null,
        ];
    }
}
