<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // TODO
    }

    /**
     * Show the empty note form.
     */
    public function create(): View
    {
        // TODO
    }

    /**
     * Save a new note for the logged-in user.
     */
    public function store(NoteRequest $request): RedirectResponse
    {
        // TODO: create through $request->user()->notes(), redirect with flash
    }

    /**
     * One note with its attachments and the upload form.
     */
    public function show(Note $note): View
    {
        // TODO: authorize view, return view
    }

    /**
     * Show the edit form for a note I own.
     */
    public function edit(Note $note): View
    {
        // TODO: authorize update, return view
    }

    /**
     * Save changes to a note I own.
     */
    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        // TODO: authorize update, update, redirect with flash
    }

    /**
     * Delete a note I own, its attachment rows and its stored files.
     */
    public function destroy(Note $note): RedirectResponse
    {
        // TODO: authorize delete, DB delete in a transaction, then files, redirect
    }
}
