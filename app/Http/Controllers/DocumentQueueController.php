<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentQueueController extends Controller
{
    /**
     * Display the incoming, on-queue, and outgoing queues.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $incomingDocuments = Document::query()
            ->forIncomingQueue($user)
            ->with(['documentCase', 'latestTransfer.forwardedBy', 'latestTransfer.fromDepartment', 'currentDepartment'])
            ->latest('updated_at')
            ->get();

        $onQueueDocuments = Document::query()
            ->forOnQueue($user)
            ->with(['documentCase', 'latestTransfer.toDepartment'])
            ->latest('updated_at')
            ->get();

        $outgoingDocuments = Document::query()
            ->forOutgoing($user)
            ->with(['documentCase', 'latestTransfer.toDepartment'])
            ->latest('updated_at')
            ->get();

        $activeDepartments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('documents.queues.index', [
            'incomingDocuments' => $incomingDocuments,
            'onQueueDocuments' => $onQueueDocuments,
            'outgoingDocuments' => $outgoingDocuments,
            'activeDepartments' => $activeDepartments,
        ]);
    }
}
