<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everything that touches file storage, in one place.
 *
 * Controllers never call Storage directly: this keeps storage rules
 * (random paths, private disk, safe download headers) in a single class
 * that is easy to test with Storage::fake('s3').
 */
class AttachmentService
{
    /**
     * Save an uploaded file to the private bucket and record it on the note.
     *
     * The file gets a random name under notes/{note_id}/; the client's
     * filename is only stored as data, never used as a path.
     */
    public function store(Note $note, UploadedFile $file): Attachment
    {
        // TODO: put file on s3 under a random path, create the attachments row
    }

    /**
     * Stream a file to the browser as a forced download.
     *
     * Sends Content-Disposition: attachment and X-Content-Type-Options: nosniff,
     * so the browser saves the file instead of rendering or executing it.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        // TODO: stream from s3 with the original name and safe headers
    }

    /**
     * Delete one attachment: database row and stored file.
     */
    public function delete(Attachment $attachment): void
    {
        // TODO: delete the row, then the file
    }

    /**
     * Delete the stored files of a note that is being deleted.
     *
     * Called after the database delete is committed: if S3 fails,
     * the worst case is an orphaned file, never a broken database record.
     */
    public function deleteAllFor(Note $note): void
    {
        // TODO: remove every stored file of this note
    }
}
