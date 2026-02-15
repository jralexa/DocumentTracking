<?php

namespace App\Http\Controllers;

use App\DocumentVersionType;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Exceptions\UnauthorizedWorkflowActionException;
use App\Http\Requests\AcceptDocumentRequest;
use App\Http\Requests\CompleteDocumentRequest;
use App\Http\Requests\ForwardDocumentRequest;
use App\Http\Requests\RecallTransferRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected DocumentWorkflowService $workflowService) {}

    /**
     * Accept a pending incoming document.
     */
    public function accept(AcceptDocumentRequest $request, Document $document): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $this->runWorkflowAction(fn () => $this->workflowService->accept($document, $user));

        return back()->with('status', 'Document accepted and moved to your action queue.');
    }

    /**
     * Forward a queued document to another department.
     */
    public function forward(ForwardDocumentRequest $request, Document $document): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $toDepartment = Department::query()->findOrFail((int) $request->validated('to_department_id'));
        $remarks = $request->validated('remarks');
        $forwardVersionType = $request->validated('forward_version_type') !== null
            ? DocumentVersionType::from($request->validated('forward_version_type'))
            : null;
        $copyKept = (bool) ($request->validated('copy_kept') ?? false);
        $copyStorageLocation = $request->validated('copy_storage_location');
        $copyPurpose = $request->validated('copy_purpose');

        $this->runWorkflowAction(function () use (
            $document,
            $user,
            $toDepartment,
            $remarks,
            $forwardVersionType,
            $copyKept,
            $copyStorageLocation,
            $copyPurpose
        ): void {
            $this->workflowService->forward(
                document: $document,
                user: $user,
                toDepartment: $toDepartment,
                remarks: $remarks,
                forwardVersionType: $forwardVersionType,
                copyKept: $copyKept,
                copyStorageLocation: $copyStorageLocation,
                copyPurpose: $copyPurpose
            );
        });

        return back()->with('status', 'Document routed successfully and custody trail updated.');
    }

    /**
     * Recall a pending outgoing transfer.
     */
    public function recall(RecallTransferRequest $request, DocumentTransfer $transfer): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $this->runWorkflowAction(fn () => $this->workflowService->recall($transfer, $user));

        return back()->with('status', 'Outgoing routing recalled successfully.');
    }

    /**
     * Mark a queued document as finished.
     */
    public function complete(CompleteDocumentRequest $request, Document $document): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $remarks = $request->validated('remarks');

        $this->runWorkflowAction(fn () => $this->workflowService->complete($document, $user, $remarks));

        return back()->with('status', 'Document marked as finished.');
    }

    /**
     * Resolve the current authenticated user.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Execute a workflow action and normalize domain exceptions.
     *
     * @param  callable():void  $callback
     */
    protected function runWorkflowAction(callable $callback): void
    {
        try {
            $callback();
        } catch (UnauthorizedWorkflowActionException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidWorkflowTransitionException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }
    }
}
