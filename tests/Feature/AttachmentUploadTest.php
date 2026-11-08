<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploading files to my own note. Allowed types and maximum size come
 * from config/attachments.php. Real storage is replaced by a fake S3 disk.
 */

beforeEach(function () {
    Storage::fake('s3');
});

it('stores an allowed file under a random path', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'client-name.txt',
        1,
        'text/plain',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $attachment = Attachment::query()->sole();

    expect($attachment->note_id)->toBe($note->id)
        ->and($attachment->disk)->toBe('s3')
        ->and($attachment->original_name)->toBe('client-name.txt')
        ->and($attachment->mime_type)->toBe('text/plain')
        ->and($attachment->size)->toBe(1024)
        ->and($attachment->path)->toStartWith("notes/{$note->id}/")
        ->and(basename($attachment->path))->not->toBe('client-name.txt');

    Storage::disk('s3')->assertExists($attachment->path);
});

it('accepts the configured file types supported by fake fixtures', function (
    string $filename,
    string $mimeType,
) {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        $filename,
        1,
        $mimeType,
    );

    $this
        ->actingAs($user)
        ->post(
            route('notes.attachments.store', $note),
            ['file' => $file],
        )
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors();

    $attachment = Attachment::query()->sole();

    expect(config('attachments.mimes'))
        ->toContain(pathinfo($filename, PATHINFO_EXTENSION));

    Storage::disk('s3')->assertExists($attachment->path);
})->with([
    'pdf' => ['document.pdf', 'application/pdf'],
    'png' => ['image.png', 'image/png'],
    'jpg' => ['image.jpg', 'image/jpeg'],
    'jpeg' => ['image.jpeg', 'image/jpeg'],
    'webp' => ['image.webp', 'image/webp'],
    'txt' => ['document.txt', 'text/plain'],
]);

it('requires a file', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        [],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('reads the allowed types from configuration', function () {
    config()->set('attachments.mimes', ['txt']);

    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'document.pdf',
        1,
        'application/pdf',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('rejects a disallowed type such as svg', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'image.svg',
        1,
        'image/svg+xml',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('rejects a spoofed extension using the reported MIME type', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'apparently-safe.pdf',
        1,
        'image/svg+xml',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('accepts a file at the configured maximum size', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'maximum-size.txt',
        config('attachments.max_kb'),
        'text/plain',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors();

    $attachment = Attachment::query()->sole();

    Storage::disk('s3')->assertExists($attachment->path);
});

it('rejects a file larger than the configured maximum size', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'too-large.txt',
        config('attachments.max_kb') + 1,
        'text/plain',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('reads the maximum file size from configuration', function () {
    config()->set('attachments.max_kb', 1);

    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $file = UploadedFile::fake()->create(
        'two-kilobytes.txt',
        2,
        'text/plain',
    );

    $response = $this->actingAs($user)->post(
        route('notes.attachments.store', $note),
        ['file' => $file],
    );

    $response->assertSessionHasErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('does not overwrite files with the same original name', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    foreach (range(1, 2) as $attempt) {
        $file = UploadedFile::fake()->create(
            'duplicate.txt',
            1,
            'text/plain',
        );

        $this
            ->actingAs($user)
            ->post(
                route('notes.attachments.store', $note),
                ['file' => $file],
            )
            ->assertSessionHasNoErrors();
    }

    $attachments = Attachment::query()->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('path')->unique())->toHaveCount(2);

    foreach ($attachments as $attachment) {
        Storage::disk('s3')->assertExists($attachment->path);
    }
});

it('downloads with attachment and nosniff headers', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/stored-file.txt",
        'original_name' => 'private-note.txt',
        'mime_type' => 'text/plain',
        'size' => 15,
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $response = $this->actingAs($user)->get(
        route('notes.attachments.download', [$note, $attachment]),
    );

    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('attachment;')
        ->and($response->streamedContent())
        ->toBe('private contents');
});

it('deletes an attachment and its stored file', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/stored-file.txt",
    ]);

    Storage::disk('s3')->put(
        $attachment->path,
        'private contents',
    );

    $response = $this->actingAs($user)->delete(
        route('notes.attachments.destroy', [$note, $attachment]),
    );

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('attachments', [
        'id' => $attachment->id,
    ]);

    Storage::disk('s3')->assertMissing($attachment->path);
});

it('redirects the owner with an error when the stored file is missing', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $attachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/missing.txt",
        'original_name' => 'missing.txt',
        'mime_type' => 'text/plain',
        'size' => 100,
    ]);

    Storage::disk('s3')->assertMissing($attachment->path);

    $response = $this->actingAs($user)->get(
        route('notes.attachments.download', [$note, $attachment]),
    );

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
        'note_id' => $note->id,
    ]);
});
