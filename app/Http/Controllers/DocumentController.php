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
use App\Services\DocumentCustodyService;
use App\TransferStatus;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected DocumentAuditService $auditService,
        protected DocumentCustodyService $custodyService
    ) {}

    /**
     * Show the document creation form.
     */
    public function create(): View
    {
        $user = auth()->user();
        $openCasesQuery = DocumentCase::query()
            ->where('status', 'open')
            ->orderByDesc('opened_at');

        if ($user instanceof User && $user->hasRole(UserRole::Guest)) {
            $openCasesQuery->where('opened_by_user_id', $user->id);
        }

        $openCases = $openCasesQuery
            ->limit(50)
            ->get(['id', 'case_number', 'title', 'owner_type', 'owner_name', 'owner_reference']);

        $latestCaseDocumentOwners = Document::query()
            ->whereIn('document_case_id', $openCases->pluck('id'))
            ->orderByDesc('id')
            ->get(['document_case_id', 'owner_district_id', 'owner_school_id'])
            ->unique('document_case_id')
            ->keyBy(static fn (Document $document): string => (string) $document->document_case_id);

        $districts = District::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'district_id', 'name']);

        $intakePrefill = session('intake_prefill');
        if (! is_array($intakePrefill)) {
            $intakePrefill = [];
        }

        return view('documents.create', [
            'openCases' => $openCases,
            'openCasePayloads' => $openCases
                ->mapWithKeys(static function (DocumentCase $openCase) use ($latestCaseDocumentOwners): array {
                    /** @var Document|null $latestOwnerSource */
                    $latestOwnerSource = $latestCaseDocumentOwners->get((string) $openCase->id);

                    return [
                        (string) $openCase->id => [
                            'case_title' => $openCase->title,
                            'owner_type' => $openCase->owner_type,
                            'owner_name' => $openCase->owner_name,
                            'owner_reference' => $openCase->owner_reference,
                            'owner_district_id' => $latestOwnerSource?->owner_district_id,
                            'owner_school_id' => $latestOwnerSource?->owner_school_id,
                        ],
                    ];
                })
                ->all(),
            'districts' => $districts,
            'schools' => $schools,
            'intakePrefill' => $intakePrefill,
        ]);
    }

    /**
     * Store a newly created document and assign it to the current user queue.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        $validated = $request->validated();
        $uploadedFiles = $request->file('attachments', []);
        $intakeDepartmentId = $this->resolveIntakeDepartmentId($user);

        abort_if($intakeDepartmentId === null, 422, 'No active intake department is configured.');
        $intakeDepartmentName = $this->resolveIntakeDepartmentName($intakeDepartmentId);
        $creationResult = $this->createDocumentWithRelatedRecords(
            validated: $validated,
            uploadedFiles: $uploadedFiles,
            user: $user,
            intakeDepartmentId: $intakeDepartmentId
        );
        $trackingNumber = $creationResult['tracking_number'];
        $document = $creationResult['document'];
        $documentCase = $creationResult['document_case'];
        $addAnother = (bool) ($validated['add_another'] ?? false);

        if ($addAnother) {
            $redirect = redirect()
                ->route('documents.create')
                ->with('status', 'Document recorded successfully. Tracking number: '.$trackingNumber)
                ->with('intake_prefill', $this->buildIntakePrefill($validated, $document, $documentCase));

            if (! $user->canProcessDocuments()) {
                $redirect->with('intake_notice', 'Submitted to '.$intakeDepartmentName.' incoming queue.');
            }

            return $redirect;
        }

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
    protected function resolveOwnerPayload(array $validated, ?DocumentCase $documentCase = null): array
    {
        if (($validated['case_mode'] ?? 'new') === 'existing' && $documentCase !== null) {
            return $this->resolveExistingCaseOwnerPayload($documentCase);
        }

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
     * Resolve owner payload directly from linked case metadata.
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
    protected function resolveExistingCaseOwnerPayload(DocumentCase $documentCase): array
    {
        $latestCaseDocument = $documentCase->latestDocument()->first(['owner_district_id', 'owner_school_id']);

        return [
            'owner_district_id' => $latestCaseDocument?->owner_district_id,
            'owner_school_id' => $latestCaseDocument?->owner_school_id,
            'owner_name' => $documentCase->owner_name,
            'owner_reference' => $documentCase->owner_reference,
            'owner_district_name' => null,
            'owner_school_name' => null,
        ];
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
    protected function resolveIntakeDepartmentId(User $user): ?int
    {
        $recordsDepartmentId = Department::query()->where('code', 'RECORDS')->value('id');

        if (! $user->canProcessDocuments()) {
            return $recordsDepartmentId
                ?? Department::query()->where('is_active', true)->orderBy('id')->value('id');
        }

        return $user->department_id
            ?? $recordsDepartmentId
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
     * Build intake prefill payload for next document encoding.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, int|string|null>
     */
    protected function buildIntakePrefill(array $validated, Document $document, DocumentCase $documentCase): array
    {
        return [
            'quick_mode' => (string) ($validated['quick_mode'] ?? '1'),
            'document_type' => $document->document_type,
            'owner_type' => $document->owner_type,
            'owner_name' => $document->owner_name,
            'owner_reference' => data_get($document->metadata, 'owner_reference'),
            'owner_district_id' => $document->owner_district_id,
            'owner_school_id' => $document->owner_school_id,
            'priority' => $document->priority,
            'source_channel' => data_get($document->metadata, 'source_channel', 'walk_in'),
            'document_classification' => data_get($document->metadata, 'document_classification', 'routine'),
            'routing_slip_number' => data_get($document->metadata, 'routing_slip_number'),
            'control_number' => data_get($document->metadata, 'control_number'),
            'received_by_name' => data_get($document->metadata, 'received_by_name'),
            'sla_days' => data_get($document->metadata, 'sla_days'),
            'preferred_case_id' => $documentCase->status === 'open' ? $documentCase->id : null,
            'preferred_case_label' => $documentCase->status === 'open'
                ? $documentCase->case_number.' - '.$documentCase->title
                : null,
        ];
    }

    /**
     * Create document case, document, and initial audit trail records.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @return array{tracking_number:string,document:Document,document_case:DocumentCase}
     */
    protected function createDocumentWithRelatedRecords(
        array $validated,
        array $uploadedFiles,
        User $user,
        int $intakeDepartmentId
    ): array {
        return DB::transaction(function () use ($validated, $uploadedFiles, $user, $intakeDepartmentId): array {
            $now = now();
            $priority = $validated['priority'] ?? 'normal';
            $canProcessDocuments = $user->canProcessDocuments();
            $dueAt = $this->resolveDueAt($validated, $now);
            $complianceMetadata = $this->resolveComplianceMetadata($validated, $now);
            $initialOwnerPayload = $this->resolveOwnerPayload($validated);
            $documentCase = $this->resolveDocumentCase(
                validated: $validated,
                ownerPayload: $initialOwnerPayload,
                now: $now,
                priority: $priority,
                user: $user
            );
            $ownerPayload = ($validated['case_mode'] ?? 'new') === 'existing'
                ? $this->resolveOwnerPayload($validated, $documentCase)
                : $initialOwnerPayload;
            $resolvedOwnerType = ($validated['case_mode'] ?? 'new') === 'existing'
                ? $documentCase->owner_type
                : $validated['owner_type'];
            $trackingNumber = $this->generateTrackingNumber($now);

            $document = Document::query()->create([
                'document_case_id' => $documentCase->id,
                'current_department_id' => $intakeDepartmentId,
                'current_user_id' => $canProcessDocuments ? $user->id : null,
                'tracking_number' => $trackingNumber,
                'reference_number' => $validated['reference_number'] ?? null,
                'subject' => $validated['subject'],
                'document_type' => $validated['document_type'],
                'owner_type' => $resolvedOwnerType,
                'owner_district_id' => $ownerPayload['owner_district_id'],
                'owner_school_id' => $ownerPayload['owner_school_id'],
                'owner_name' => $ownerPayload['owner_name'],
                'status' => $canProcessDocuments ? DocumentWorkflowStatus::OnQueue : DocumentWorkflowStatus::Outgoing,
                'priority' => $priority,
                'received_at' => $now,
                'due_at' => $dueAt,
                'metadata' => [
                    'created_from' => 'web_form',
                    'owner_reference' => $ownerPayload['owner_reference'],
                    'owner_district_name' => $ownerPayload['owner_district_name'],
                    'owner_school_name' => $ownerPayload['owner_school_name'],
                    'source_channel' => $complianceMetadata['source_channel'],
                    'document_classification' => $complianceMetadata['document_classification'],
                    'routing_slip_number' => $complianceMetadata['routing_slip_number'],
                    'control_number' => $complianceMetadata['control_number'],
                    'received_by_name' => $complianceMetadata['received_by_name'],
                    'received_at' => $complianceMetadata['received_at'],
                    'sla_days' => $complianceMetadata['sla_days'],
                    'sla_applied' => $complianceMetadata['sla_applied'],
                ],
                'is_returnable' => (bool) ($validated['is_returnable'] ?? false),
                'return_deadline' => $validated['return_deadline'] ?? null,
            ]);

            $intakeDepartment = Department::query()->find($intakeDepartmentId);
            $initialCustodian = ($canProcessDocuments && (int) $user->department_id === $intakeDepartmentId)
                ? $user
                : null;

            $this->custodyService->assignOriginalCustody(
                document: $document,
                department: $intakeDepartment,
                custodian: $initialCustodian,
                purpose: 'Initial intake custody assignment.'
            );

            $document->items()->create([
                'name' => $validated['item_name'] ?? $validated['subject'],
                'item_type' => 'main',
                'status' => 'active',
                'quantity' => 1,
                'sort_order' => 0,
            ]);

            $this->storeDocumentAttachments(
                document: $document,
                uploadedFiles: $uploadedFiles,
                user: $user
            );

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

            return [
                'tracking_number' => $trackingNumber,
                'document' => $document,
                'document_case' => $documentCase,
            ];
        });
    }

    /**
     * Resolve due date from explicit input or SLA fallback rules.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveDueAt(array $validated, Carbon $now): ?Carbon
    {
        if (! empty($validated['due_at'])) {
            return Carbon::parse((string) $validated['due_at'])->endOfDay();
        }

        $quickMode = (bool) ($validated['quick_mode'] ?? false);
        if ($quickMode) {
            return null;
        }

        $slaDays = isset($validated['sla_days']) ? (int) $validated['sla_days'] : $this->defaultSlaDays($validated);

        return $now->copy()->addDays($slaDays)->endOfDay();
    }

    /**
     * Build intake compliance metadata captured at receiving.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *   source_channel:string,
     *   document_classification:string,
     *   routing_slip_number:?string,
     *   control_number:?string,
     *   received_by_name:?string,
     *   received_at:string,
     *   sla_days:?int,
     *   sla_applied:bool
     * }
     */
    protected function resolveComplianceMetadata(array $validated, Carbon $now): array
    {
        $sourceChannel = (string) ($validated['source_channel'] ?? 'walk_in');
        $classification = (string) ($validated['document_classification'] ?? 'routine');
        $receivedAt = isset($validated['received_at'])
            ? Carbon::parse((string) $validated['received_at'])
            : $now;
        $slaDays = isset($validated['sla_days']) ? (int) $validated['sla_days'] : null;

        return [
            'source_channel' => $sourceChannel,
            'document_classification' => $classification,
            'routing_slip_number' => $validated['routing_slip_number'] ?? null,
            'control_number' => $validated['control_number'] ?? null,
            'received_by_name' => $validated['received_by_name'] ?? null,
            'received_at' => $receivedAt->toIso8601String(),
            'sla_days' => $slaDays,
            'sla_applied' => empty($validated['due_at']) && ! (bool) ($validated['quick_mode'] ?? false),
        ];
    }

    /**
     * Resolve default SLA days from document type and priority.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function defaultSlaDays(array $validated): int
    {
        $baseDays = match ((string) ($validated['document_type'] ?? 'for_processing')) {
            'communication' => 3,
            'submission' => 5,
            'request' => 7,
            default => 10,
        };

        $priorityAdjustment = match ((string) ($validated['priority'] ?? 'normal')) {
            'urgent' => -2,
            'high' => -1,
            'low' => 2,
            default => 0,
        };

        return max(1, $baseDays + $priorityAdjustment);
    }

    /**
     * Persist uploaded document attachments and mirror them as attachment items.
     *
     * @param  array<int, UploadedFile>  $uploadedFiles
     */
    protected function storeDocumentAttachments(Document $document, array $uploadedFiles, User $user): void
    {
        foreach ($uploadedFiles as $uploadedFile) {
            $path = $uploadedFile->store('document-attachments/'.$document->id, 'public');

            $document->attachments()->create([
                'uploaded_by_user_id' => $user->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size_bytes' => (int) $uploadedFile->getSize(),
            ]);

            $document->items()->create([
                'name' => $uploadedFile->getClientOriginalName(),
                'item_type' => 'attachment',
                'status' => 'active',
                'quantity' => 1,
                'metadata' => [
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $uploadedFile->getClientMimeType(),
                    'size_bytes' => (int) $uploadedFile->getSize(),
                ],
            ]);
        }
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
    protected function resolveDocumentCase(array $validated, array $ownerPayload, Carbon $now, string $priority, User $user): DocumentCase
    {
        if (($validated['case_mode'] ?? 'new') === 'existing') {
            $openCaseQuery = DocumentCase::query()
                ->lockForUpdate()
                ->where('status', 'open');

            if ($user->hasRole(UserRole::Guest)) {
                $openCaseQuery->where('opened_by_user_id', $user->id);
            }

            $openCase = $openCaseQuery->find((int) $validated['document_case_id']);

            if ($openCase === null) {
                throw ValidationException::withMessages([
                    'document_case_id' => 'Selected case is not open or not available for your account.',
                ]);
            }

            return $openCase;
        }

        return DocumentCase::query()->create([
            'case_number' => $this->generateCaseNumber($now),
            'title' => $validated['case_title'] ?? $validated['subject'],
            'owner_type' => $validated['owner_type'],
            'owner_name' => $ownerPayload['owner_name'],
            'owner_reference' => $ownerPayload['owner_reference'],
            'opened_by_user_id' => $user->id,
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
