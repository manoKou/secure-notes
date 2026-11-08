<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A private note owned by one user.
 *
 * Uses ULIDs instead of 1, 2, 3... so note URLs can't be guessed.
 * Ownership is checked in NotePolicy, never here.
 */
class Note extends Model
{
    use HasFactory, HasUlids;

    /**
     * Fields a form may set. user_id is deliberately missing:
     * it is always taken from the logged-in user, never from the request.
     */
    protected $fillable = ['title', 'body'];

    /**
     * The owner of this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Files attached to this note. Deleted together with the note.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
