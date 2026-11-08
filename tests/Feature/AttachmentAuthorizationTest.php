<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Attachments are reachable only through their own note and only by
 * that note's owner. Unauthorized requests return 404.
 */

beforeEach(function () {
    Storage::fake('s3');
});

it('redirects guests to login from every attachment route', function () {
    $note = Note::factory()->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/private.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $responses = [
        $this->post(
            route('notes.attachments.store', $note),
            [
                'file' => UploadedFile::fake()->create(
                    'guest.txt',
                    1,
                    'text/plain',
                ),
            ],
        ),
        $this->get(
            route('notes.attachments.download', [$note, $attachment]),
        ),
        $this->delete(
            route('notes.attachments.destroy', [$note, $attachment]),
        ),
    ];

    foreach ($responses as $response) {
        $response->assertRedirect(route('login'));
    }

    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
    ]);

    Storage::disk('s3')->assertExists($attachment->path);
});

it('returns 404 when another user downloads my file', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/private.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $this
        ->actingAs($otherUser)
        ->get(route(
            'notes.attachments.download',
            [$note, $attachment],
        ))
        ->assertNotFound()
        ->assertDontSee('private contents');
});

it('returns 404 when another user deletes my file', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/private.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $this
        ->actingAs($otherUser)
        ->delete(route(
            'notes.attachments.destroy',
            [$note, $attachment],
        ))
        ->assertNotFound();

    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
    ]);

    Storage::disk('s3')->assertExists($attachment->path);
});

it('returns 404 when another user uploads to my note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $file = UploadedFile::fake()->create(
        'unauthorized.txt',
        1,
        'text/plain',
    );

    $this
        ->actingAs($otherUser)
        ->post(
            route('notes.attachments.store', $note),
            ['file' => $file],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('attachments', 0);
});

it('returns 404 before validating another users upload', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $this
        ->actingAs($otherUser)
        ->post(
            route('notes.attachments.store', $note),
            [],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('attachments', 0);
});

it('returns 404 when downloading an attachment through a different note', function () {
    $user = User::factory()->create();

    $correctNote = Note::factory()->for($user)->create();
    $differentNote = Note::factory()->for($user)->create();

    $attachment = Attachment::factory()->for($correctNote)->create([
        'disk' => 's3',
        'path' => "notes/{$correctNote->id}/private.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $this
        ->actingAs($user)
        ->get(route(
            'notes.attachments.download',
            [$differentNote, $attachment],
        ))
        ->assertNotFound();
});

it('returns 404 when deleting an attachment through a different note', function () {
    $user = User::factory()->create();

    $correctNote = Note::factory()->for($user)->create();
    $differentNote = Note::factory()->for($user)->create();

    $attachment = Attachment::factory()->for($correctNote)->create([
        'disk' => 's3',
        'path' => "notes/{$correctNote->id}/private.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $this
        ->actingAs($user)
        ->delete(route(
            'notes.attachments.destroy',
            [$differentNote, $attachment],
        ))
        ->assertNotFound();

    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
        'note_id' => $correctNote->id,
    ]);

    Storage::disk('s3')->assertExists($attachment->path);
});
