<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Models\Attachment;
use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download and delete files of a note.
 *
 * Routes are nested (/notes/{note}/attachments/{attachment}) with scoped
 * bindings, so an attachment is only found through the note it belongs to.
 * Ownership is checked on the note via NotePolicy.
 */
class AttachmentController extends Controller
{
    /**
     * All storage work is delegated to AttachmentService.
     */
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    /**
     * Upload a file to a note I own.
     */
    public function store(AttachmentRequest $request, Note $note): RedirectResponse
    {
        // TODO: authorize update on the note, store via service, redirect with flash
    }

    /**
     * Download a file of a note I own (forced download, never inline).
     */
    public function download(Note $note, Attachment $attachment): StreamedResponse
    {
        // TODO: authorize view on the note, return the service's download
    }

    /**
     * Delete a file from a note I own.
     */
    public function destroy(Note $note, Attachment $attachment): RedirectResponse
    {
        // TODO: authorize update on the note, delete via service, redirect with flash
    }
}
