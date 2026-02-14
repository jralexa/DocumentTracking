<?php

namespace App\Http\Controllers;

use App\DocumentEventType;
use App\DocumentWorkflowStatus;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\DocumentCase;
use App\Services\DocumentAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentAuditService $auditService)
    {
    }

    /**
     * Show the document creation form.
     */
    public function create(): View
    {
        return view('documents.create');
    }

    /**
     * Store a newly created document and assign it to the current user queue.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_if($user->department_id === null, 403, 'User must belong to a department to create documents.');

        $validated = $request->validated();
        DB::transaction(function () use ($validated, $user): void {
            $now = now();
            $caseNumber = $this->generateCaseNumber($now);
            $priority = $validated['priority'] ?? 'normal';

            $documentCase = DocumentCase::query()->create([
                'case_number' => $caseNumber,
                'title' => $validated['case_title'] ?? $validated['subject'],
                'owner_type' => $validated['owner_type'],
                'owner_name' => $validated['owner_name'],
                'owner_reference' => $validated['owner_reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => 'open',
                'priority' => $priority,
                'opened_at' => $now,
            ]);

            $trackingNumber = $this->generateTrackingNumber($now);

            $document = Document::query()->create([
                'document_case_id' => $documentCase->id,
                'current_department_id' => $user->department_id,
                'current_user_id' => $user->id,
                'tracking_number' => $trackingNumber,
                'reference_number' => $validated['reference_number'] ?? null,
                'subject' => $validated['subject'],
                'document_type' => $validated['document_type'],
                'owner_type' => $validated['owner_type'],
                'owner_name' => $validated['owner_name'],
                'status' => DocumentWorkflowStatus::OnQueue,
                'priority' => $priority,
                'received_at' => $now,
                'due_at' => $validated['due_at'] ?? null,
                'metadata' => [
                    'created_from' => 'web_form',
                    'owner_reference' => $validated['owner_reference'] ?? null,
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
        });

        return redirect()
            ->route('documents.queues.index')
            ->with('status', 'Document created and added to your queue.');
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
}
