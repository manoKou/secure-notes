<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\AttachmentService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Deleting an account (Settings → Delete account): the database cascade
     * removes the notes and attachment rows, but not the files in S3.
     * Load them before the delete, remove them after it succeeded,
     * the same order as deleting a single note.
     */
    protected static function booted(): void
    {
        static::deleting(fn (User $user) => $user->load('notes.attachments'));

        static::deleted(function (User $user) {
            $attachments = app(AttachmentService::class);

            foreach ($user->notes as $note) {
                $attachments->deleteAllFor($note);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * This makes $request->user()->notes() work,
     * so every query starts from the logged-in user.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
