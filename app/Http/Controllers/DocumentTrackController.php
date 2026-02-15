<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTrackController extends Controller
{
    /**
     * Show public track document search and limited result details.
     */
    public function public(Request $request): View
    {
        return $this->renderTrackView($request, 'documents.track.public', false);
    }

    /**
     * Show track document search and result details.
     */
    public function index(Request $request): View
    {
        return $this->renderTrackView($request, 'documents.track.index', true);
    }

    /**
     * Render tracking view with searched document payload.
     */
    protected function renderTrackView(Request $request, string $view, bool $includeInternalDetails): View
    {
        $trackingNumber = trim((string) $request->query('tracking_number', ''));
        $document = $this->findTrackableDocument($trackingNumber, $includeInternalDetails);

        return view($view, [
            'trackingNumber' => $trackingNumber,
            'document' => $document,
        ]);
    }

    /**
     * Find a document by base or split display tracking number.
     */
    protected function findTrackableDocument(string $trackingNumber, bool $includeInternalDetails): ?Document
    {
        if ($trackingNumber === '') {
            return null;
        }

        $query = Document::query()->with($this->baseTrackingRelations());

        $query->where(function ($builder) use ($trackingNumber): void {
            $builder
                ->where('tracking_number', $trackingNumber)
                ->orWhere('metadata->display_tracking', $trackingNumber);
        });

        if ($includeInternalDetails) {
            $query->with($this->internalTrackingRelations());
        }

        return $query->first();
    }

    /**
     * Get base relationships required by public and authenticated tracking views.
     *
     * @return array<int, string>
     */
    protected function baseTrackingRelations(): array
    {
        return [
            'documentCase',
            'currentDepartment',
            'currentUser',
            'originalCurrentDepartment',
            'transfers.fromDepartment',
            'transfers.toDepartment',
            'transfers.forwardedBy',
            'transfers.acceptedBy',
        ];
    }

    /**
     * Get internal-only relationships for authenticated tracking view.
     *
     * @return array<int, string>
     */
    protected function internalTrackingRelations(): array
    {
        return [
            'copies.department',
            'custodies.department',
            'custodies.user',
        ];
    }
}
