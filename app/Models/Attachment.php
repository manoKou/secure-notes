<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * A private file attached to one note.
 *
 * The file itself lives in the private S3 bucket under a random path;
 * this row stores where it is and the original name for downloading.
 * There is no user() on purpose: ownership flows through the note.
 */
class Attachment extends Model
{
    use HasFactory, HasUlids;

    /**
     * Fields set by AttachmentService when a file is stored.
     */
    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size'];

    /**
     * The note this file belongs to (and through it, the owner).
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
