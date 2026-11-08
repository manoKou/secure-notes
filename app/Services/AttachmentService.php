<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Everything that touches file storage, in one place.
 *
 * Controllers never call Storage directly: this keeps storage rules
 * (random paths, private disk, safe download headers) in a single class
 * that is easy to test with Storage::fake('s3').
 */
class AttachmentService
{
    /** All new files go to the private S3 disk. */
    private const DISK = 's3';

    /**
     * Save an uploaded file to the private bucket and record it on the note.
     *
     * The file gets a random name under notes/{note_id}/; the client's
     * filename is only stored as data, never used as a path.
     */
    public function store(Note $note, UploadedFile $file): Attachment
    {
        // store() picks a random 40-char name + an extension detected from the
        // content, so the client's filename never becomes part of the path.
        $path = $file->store("notes/{$note->id}", self::DISK);

        if ($path === false) {
            throw new RuntimeException('Could not write the file to storage.');
        }

        try {
            return $note->attachments()->create([
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $e) {
            // The row failed: remove the file so it does not become an orphan.
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * Stream a file to the browser as a forced download.
     *
     * Sends Content-Disposition: attachment and X-Content-Type-Options: nosniff,
     * so the browser saves the file instead of rendering or executing it.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        // A missing object would otherwise crash while building the response (500).
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            throw new FileNotFoundException("Attachment file missing: {$attachment->path}");
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Delete one attachment: database row and stored file.
     */
    public function delete(Attachment $attachment): void
    {
        $attachment->delete();
        $this->deleteFile($attachment);
    }

    /**
     * Delete the stored files of a note that is being deleted.
     *
     * Called after the database delete is committed: if S3 fails,
     * the worst case is an orphaned file, never a broken database record.
     */
    public function deleteAllFor(Note $note): void
    {
        foreach ($note->attachments as $attachment) {
            $this->deleteFile($attachment);
        }
    }

    /**
     * Remove a stored file. The database row is already gone at this point,
     * so if storage fails we log the path: the file can still be cleaned up
     * later instead of being lost track of (Codex review, L2).
     */
    private function deleteFile(Attachment $attachment): void
    {
        if (! Storage::disk($attachment->disk)->delete($attachment->path)) {
            Log::warning('Attachment file could not be deleted from storage.', [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ]);
        }
    }
}
