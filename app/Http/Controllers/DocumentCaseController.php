<?php

namespace App\Http\Controllers;

use App\Models\DocumentCase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentCaseController extends Controller
{
    /**
     * Display case listing.
     */
    public function index(Request $request): View
    {
        $cases = $this->cases();

        return view('cases.index', [
            'cases' => $cases,
        ]);
    }

    /**
     * Show a specific case and linked documents.
     */
    public function show(DocumentCase $documentCase): View
    {
        $this->loadCaseDocuments($documentCase);
        $statusSummary = $this->statusSummary($documentCase->documents);
        $departmentSummary = $this->departmentSummary($documentCase->documents);

        return view('cases.show', [
            'documentCase' => $documentCase,
            'statusSummary' => $statusSummary,
            'departmentSummary' => $departmentSummary,
        ]);
    }

    /**
     * Get paginated case listing.
     */
    protected function cases(): LengthAwarePaginator
    {
        return DocumentCase::query()
            ->withCount('documents')
            ->orderByDesc('opened_at')
            ->paginate(15);
    }

    /**
     * Load case documents and related associations for case detail view.
     */
    protected function loadCaseDocuments(DocumentCase $documentCase): void
    {
        $documentCase->load([
            'documents.currentDepartment',
            'documents.currentUser',
            'documents.originalCurrentDepartment',
            'documents.latestTransfer.toDepartment',
            'documents.latestTransfer.fromDepartment',
            'documents.outgoingRelationships.relatedDocument',
            'documents.incomingRelationships.sourceDocument',
        ]);
    }

    /**
     * Build case status summary map.
     */
    protected function statusSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn ($document) => $document->status->value)
            ->map(fn ($group) => $group->count())
            ->sortKeys();
    }

    /**
     * Build case department summary map.
     */
    protected function departmentSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn ($document) => $document->currentDepartment?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->count())
            ->sortKeys();
    }
}
