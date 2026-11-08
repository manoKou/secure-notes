<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates the note form, for both create and edit.
 *
 * One class instead of Store/Update: the rules are identical,
 * and two copies would drift apart over time.
 */
class NoteRequest extends FormRequest
{
    /**
     * Editing: the user must own the note. This runs before rules(), so a
     * non-owner gets 404 even with invalid input. Creating: always allowed.
     */
    public function authorize(): bool
    {
        $note = $this->route('note');

        if ($note !== null) {
            Gate::authorize('update', $note); // NotePolicy → 404 for non-owners
        }

        return true;
    }

    /**
     * Title required (max 255 chars); body optional (max 10,000 chars).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
