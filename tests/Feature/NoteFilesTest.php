<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Files submitted directly through the note create and edit forms.
 */
beforeEach(function () {
    Storage::fake('s3');
});

it('creates a note with two files', function () {
    $user = User::factory()->create();

    $files = [
        UploadedFile::fake()->create(
            'first.txt',
            1,
            'text/plain',
        ),
        UploadedFile::fake()->create(
            'second.pdf',
            1,
            'application/pdf',
        ),
    ];

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Note with files',
        'body' => 'Two private attachments.',
        'files' => $files,
    ]);

    $note = Note::query()->sole();
    $attachments = Attachment::query()->get();

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Note::query()->count())->toBe(1)
        ->and($attachments)->toHaveCount(2)
        ->and($attachments->pluck('note_id')->unique()->all())
        ->toBe([$note->id])
        ->and($attachments->pluck('path')->unique())
        ->toHaveCount(2);

    foreach ($attachments as $attachment) {
        expect($attachment->path)
            ->toStartWith("notes/{$note->id}/");

        Storage::disk('s3')->assertExists($attachment->path);
    }
});

it('does not create a note when one submitted file is disallowed', function () {
    $user = User::factory()->create();

    $files = [
        UploadedFile::fake()->create(
            'valid.txt',
            1,
            'text/plain',
        ),
        UploadedFile::fake()->create(
            'disallowed.svg',
            1,
            'image/svg+xml',
        ),
    ];

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Invalid file collection',
        'body' => 'This note must not be created.',
        'files' => $files,
    ]);

    $response->assertSessionHasErrors('files.1');

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('attachments', 0);

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

it('does not create a note when the configured file count is exceeded', function () {
    $user = User::factory()->create();

    $fileCount = config('attachments.max_files') + 1;

    $files = array_map(
        fn (int $number) => UploadedFile::fake()->create(
            "file-{$number}.txt",
            1,
            'text/plain',
        ),
        range(1, $fileCount),
    );

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Too many files',
        'body' => 'This note must not be created.',
        'files' => $files,
    ]);

    $response->assertSessionHasErrors('files');

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('attachments', 0);

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

it('creates a note without files', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Note without files',
        'body' => 'Attachments are optional.',
    ]);

    $note = Note::query()->sole();

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($note->user_id)->toBe($user->id);

    $this->assertDatabaseCount('notes', 1);
    $this->assertDatabaseCount('attachments', 0);

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

it('updates a note and adds files without removing existing attachments', function () {
    $user = User::factory()->create();

    $note = Note::factory()->for($user)->create([
        'title' => 'Old title',
        'body' => 'Old body',
    ]);

    $existingAttachment = Attachment::factory()->for($note)->create([
        'disk' => 's3',
        'path' => "notes/{$note->id}/existing.txt",
        'original_name' => 'existing.txt',
        'mime_type' => 'text/plain',
        'size' => 16,
    ]);

    Storage::disk('s3')->put(
        $existingAttachment->path,
        'existing content',
    );

    $files = [
        UploadedFile::fake()->create(
            'first-new.txt',
            1,
            'text/plain',
        ),
        UploadedFile::fake()->create(
            'second-new.pdf',
            1,
            'application/pdf',
        ),
    ];

    $response = $this->actingAs($user)->put(
        route('notes.update', $note),
        [
            'title' => 'Updated title',
            'body' => 'Updated body',
            'files' => $files,
        ],
    );

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $note->refresh();

    expect($note->title)->toBe('Updated title')
        ->and($note->body)->toBe('Updated body');

    $this->assertDatabaseHas('attachments', [
        'id' => $existingAttachment->id,
        'note_id' => $note->id,
    ]);

    $attachments = $note->attachments()->get();

    expect($attachments)->toHaveCount(3);

    foreach ($attachments as $attachment) {
        expect($attachment->path)
            ->toStartWith("notes/{$note->id}/");

        Storage::disk('s3')->assertExists($attachment->path);
    }
});

it('returns 404 when a non-owner updates a note with files', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $note = Note::factory()->for($owner)->create([
        'title' => 'Owners title',
        'body' => 'Owners body',
    ]);

    $file = UploadedFile::fake()->create(
        'unauthorized.txt',
        1,
        'text/plain',
    );

    $this
        ->actingAs($otherUser)
        ->put(
            route('notes.update', $note),
            [
                'title' => 'Unauthorized title',
                'body' => 'Unauthorized body',
                'files' => [$file],
            ],
        )
        ->assertNotFound();

    $note->refresh();

    expect($note->title)->toBe('Owners title')
        ->and($note->body)->toBe('Owners body');

    $this->assertDatabaseCount('attachments', 0);

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

it('keeps the created note and reports an error when storage fails', function () {
    $user = User::factory()->create();

    $service = Mockery::mock(AttachmentService::class)
        ->makePartial();

    $service
        ->shouldReceive('store')
        ->once()
        ->ordered()
        ->passthru();

    $service
        ->shouldReceive('store')
        ->once()
        ->ordered()
        ->andThrow(new RuntimeException('Simulated storage failure.'));

    $this->app->instance(AttachmentService::class, $service);

    $files = [
        UploadedFile::fake()->create(
            'stored-before-failure.txt',
            1,
            'text/plain',
        ),
        UploadedFile::fake()->create(
            'fails.txt',
            1,
            'text/plain',
        ),
    ];

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Saved despite storage failure',
        'body' => 'The note and first file should remain.',
        'files' => $files,
    ]);

    $note = Note::query()->sole();
    $attachment = Attachment::query()->sole();

    $response
        ->assertRedirect(route('notes.show', $note))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    expect($note->user_id)->toBe($user->id)
        ->and($note->title)->toBe('Saved despite storage failure')
        ->and($attachment->note_id)->toBe($note->id)
        ->and($attachment->original_name)
        ->toBe('stored-before-failure.txt')
        ->and($attachment->path)
        ->toStartWith("notes/{$note->id}/");

    Storage::disk('s3')->assertExists($attachment->path);
});
