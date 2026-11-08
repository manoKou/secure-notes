<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The single source of truth for "is this my note?".
 *
 * Also covers attachments: an attachment belongs to exactly one note,
 * and scoped route bindings make sure it really belongs to that note.
 * Refusals return 404, not 403, so we never reveal that a note exists.
 */
class NotePolicy
{
    /**
     * May the user open this note (and download its files)?
     */
    public function view(User $user, Note $note): Response
    {
        return $this->owns($user, $note);
    }

    /**
     * May the user edit this note (and upload files to it)?
     */
    public function update(User $user, Note $note): Response
    {
        return $this->owns($user, $note);
    }

    /**
     * May the user delete this note (and its files)?
     */
    public function delete(User $user, Note $note): Response
    {
        return $this->owns($user, $note);
    }

    /**
     * Owner → allowed. Anyone else → 404, as if the note didn't exist.
     */
    private function owns(User $user, Note $note): Response
    {
        return $note->user()->is($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
