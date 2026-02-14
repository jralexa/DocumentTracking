<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Exceptions\UnauthorizedWorkflowActionException;
use App\Http\Requests\AcceptDocumentRequest;
use App\Http\Requests\ForwardDocumentRequest;
use App\Http\Requests\RecallTransferRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentWorkflowService $workflowService)
    {
    }

    /**
     * Accept a pending incoming document.
     */
    public function accept(AcceptDocumentRequest $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $this->workflowService->accept($document, $user);
        } catch (UnauthorizedWorkflowActionException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidWorkflowTransitionException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }

        return back()->with('status', 'Document accepted and moved to your queue.');
    }

    /**
     * Forward a queued document to another department.
     */
    public function forward(ForwardDocumentRequest $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $toDepartment = Department::query()->findOrFail((int) $request->validated('to_department_id'));
        $remarks = $request->validated('remarks');

        try {
            $this->workflowService->forward($document, $user, $toDepartment, $remarks);
        } catch (UnauthorizedWorkflowActionException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidWorkflowTransitionException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }

        return back()->with('status', 'Document forwarded successfully.');
    }

    /**
     * Recall a pending outgoing transfer.
     */
    public function recall(RecallTransferRequest $request, DocumentTransfer $transfer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $this->workflowService->recall($transfer, $user);
        } catch (UnauthorizedWorkflowActionException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidWorkflowTransitionException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }

        return back()->with('status', 'Outgoing transfer recalled successfully.');
    }
}
