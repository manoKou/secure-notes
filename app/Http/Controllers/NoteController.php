<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD for the logged-in user's notes.
 *
 * Thin by design: validation is in NoteRequest, ownership in NotePolicy,
 * file handling in AttachmentService. Each method only connects them.
 */
class NoteController extends Controller
{
    /**
     * AttachmentService is injected by Laravel; needed when a note
     * is deleted, so its files are deleted too.
     */
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    /**
     * List my notes, newest first. Queried through the user,
     * so other users' notes can never appear.
     */
    public function index(Request $request): View
    {
        $notes = $request->user()->notes()
            ->withCount('attachments')
            ->latest()
            ->paginate(20);

        return view('notes.index', compact('notes'));
    }

    /**
     * Show the empty note form.
     */
    public function create(): View
    {
        return view('notes.create');
    }

    /**
     * Save a new note for the logged-in user.
     */
    public function store(NoteRequest $request): RedirectResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return redirect()->route('notes.show', $note)->with('success', __('Note created.'));
    }

    /**
     * One note with its attachments and the upload form.
     */
    public function show(Note $note): View
    {
        Gate::authorize('view', $note);
        $note->load('attachments');

        return view('notes.show', compact('note'));
    }

    /**
     * Show the edit form for a note I own.
     */
    public function edit(Note $note): View
    {
        Gate::authorize('update', $note);

        return view('notes.edit', compact('note'));
    }

    /**
     * Save changes to a note I own.
     */
    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $note->update($request->validated());

        return redirect()->route('notes.show', $note)->with('success', __('Note saved.'));
    }

    /**
     * Delete a note I own, its attachment rows and its stored files.
     */
    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        // Load the files first: after the delete, the rows are gone.
        $note->load('attachments');

        // One DELETE; the database cascades to the attachment rows in the same
        // statement, so it is atomic. Files are removed only after it succeeded.
        $note->delete();
        $this->attachments->deleteAllFor($note);

        return redirect()->route('notes.index')->with('success', __('Note deleted.'));
    }
}
