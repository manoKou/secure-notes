<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Models\Attachment;
use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Upload a file to a note I own.
     */
    public function store(AttachmentRequest $request, Note $note): RedirectResponse
    {
        // Ownership was already checked in AttachmentRequest (404 before validation).
        try {
            $this->attachments->store($note, $request->file('file'));
        } catch (Throwable $e) {
            report($e); // logged for us, not shown to the user

            return back()->with('error', __('The file could not be uploaded. Please try again.'));
        }

        return redirect()->route('notes.show', $note)->with('success', __('File uploaded.'));
    }

    /**
     * Download a file of a note I own (forced download, never inline).
     */
    public function download(Note $note, Attachment $attachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $note);

        try {
            return $this->attachments->download($attachment);
        } catch (Throwable $e) {
            report($e); // the path is in the log for us

            return redirect()->route('notes.show', $note)
                ->with('error', __('This file could not be downloaded. It may be missing, or storage is unavailable.'));
        }
    }

    /**
     * Delete a file from a note I own.
     */
    public function destroy(Note $note, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $note);
        $this->attachments->delete($attachment);

        return redirect()->route('notes.show', $note)->with('success', __('File deleted.'));
    }
}
