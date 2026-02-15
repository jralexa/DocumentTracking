<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentQueueController extends Controller
{
    /**
     * Display the incoming, on-queue, and outgoing queues.
     */
    public function index(Request $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $incomingDocuments = $this->incomingDocuments($user);
        $onQueueDocuments = $this->onQueueDocuments($user);
        $outgoingDocuments = $this->outgoingDocuments($user);
        $activeDepartments = $this->activeDepartments();

        return view('documents.queues.index', [
            'incomingDocuments' => $incomingDocuments,
            'onQueueDocuments' => $onQueueDocuments,
            'outgoingDocuments' => $outgoingDocuments,
            'activeDepartments' => $activeDepartments,
        ]);
    }

    /**
     * Resolve authenticated user from request.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Get incoming queue documents for the acting user.
     */
    protected function incomingDocuments(User $user): LengthAwarePaginator
    {
        return Document::query()
            ->forIncomingQueue($user)
            ->with(['documentCase', 'latestTransfer.forwardedBy', 'latestTransfer.fromDepartment', 'currentDepartment'])
            ->latest('updated_at')
            ->paginate(10, ['*'], 'incoming_page');
    }

    /**
     * Get on-queue documents assigned to the acting user.
     */
    protected function onQueueDocuments(User $user): LengthAwarePaginator
    {
        return Document::query()
            ->forOnQueue($user)
            ->with(['documentCase', 'latestTransfer.toDepartment'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('updated_at')
            ->paginate(10, ['*'], 'on_queue_page');
    }

    /**
     * Get outgoing pending documents forwarded by the acting user.
     */
    protected function outgoingDocuments(User $user): LengthAwarePaginator
    {
        return Document::query()
            ->forOutgoing($user)
            ->with(['documentCase', 'latestTransfer.toDepartment'])
            ->latest('updated_at')
            ->paginate(10, ['*'], 'outgoing_page');
    }

    /**
     * Get active departments used in queue route options.
     *
     * @return Collection<int, Department>
     */
    protected function activeDepartments(): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
