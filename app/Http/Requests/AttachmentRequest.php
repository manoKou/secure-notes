<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates a single file upload.
 *
 * Type and size come from config/attachments.php, so validation,
 * the upload form hint and the tests always use the same values.
 */
class AttachmentRequest extends FormRequest
{
    /**
     * The user must own the note. Runs before rules(), so a non-owner gets
     * 404, not "file too large" (same lesson as NoteRequest).
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('note'));

        return true;
    }

    /**
     * One required file, allowed type, within the size limit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', ...self::fileRules()],
        ];
    }

    /**
     * The rules for a single uploaded file, reused by NoteRequest
     * so the note form and the upload form can never disagree.
     *
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', config('attachments.mimes')),
            'max:'.config('attachments.max_kb'),
        ];
    }
}
