<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single file upload.
 *
 * Type and size come from config/attachments.php, so validation,
 * the upload form hint and the tests always use the same values.
 */
class AttachmentRequest extends FormRequest
{
    /**
     * Always true: ownership of the target note is checked by NotePolicy.
     */
    public function authorize(): bool
    {
        // TODO
    }

    /**
     * One required file, allowed type, within the size limit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // TODO: file → required, file, mimes from config, max from config
    }
}
