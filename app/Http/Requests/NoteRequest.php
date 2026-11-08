<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the note form, for both create and edit.
 *
 * One class instead of Store/Update: the rules are identical,
 * and two copies would drift apart over time.
 */
class NoteRequest extends FormRequest
{
    /**
     * Always true: ownership is checked by NotePolicy in the controller,
     * so authorization lives in one place only.
     */
    public function authorize(): bool
    {
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
