<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentAttachmentController extends Controller
{
    /**
     * Download a stored attachment for a document.
     */
    public function download(Request $request, Document $document, DocumentAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_if($attachment->document_id !== $document->id, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
