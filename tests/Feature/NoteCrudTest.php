<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Note CRUD for the owner. Authorization is tested separately.
 */
it('lists only my notes', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $olderNote = Note::factory()->for($user)->create([
        'title' => 'My older note',
        'created_at' => now()->subMinute(),
    ]);

    $newerNote = Note::factory()->for($user)->create([
        'title' => 'My newer note',
        'created_at' => now(),
    ]);

    $otherNote = Note::factory()->for($otherUser)->create([
        'title' => 'Another users private note',
    ]);

    $response = $this->actingAs($user)->get(route('notes.index'));

    $response
        ->assertOk()
        ->assertSee($olderNote->title)
        ->assertSee($newerNote->title)
        ->assertDontSee($otherNote->title)
        ->assertSeeInOrder([
            $newerNote->title,
            $olderNote->title,
        ]);
});

it('creates a note for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Private project notes',
        'body' => 'Only the authenticated user should own this note.',
        'user_id' => $otherUser->id,
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('notes', [
        'user_id' => $user->id,
        'title' => 'Private project notes',
        'body' => 'Only the authenticated user should own this note.',
    ]);

    $this->assertDatabaseMissing('notes', [
        'user_id' => $otherUser->id,
        'title' => 'Private project notes',
    ]);
});

it('creates a note with a null body', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Title only',
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('notes', [
        'user_id' => $user->id,
        'title' => 'Title only',
        'body' => null,
    ]);
});

it('rejects a note without a title', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('notes.create'))
        ->post(route('notes.store'), [
            'body' => 'A body without a title.',
        ]);

    $response
        ->assertRedirect(route('notes.create'))
        ->assertSessionHasErrors('title');

    $this->assertDatabaseCount('notes', 0);
});

it('requires the title to be a string', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => ['not', 'a', 'string'],
        'body' => 'Valid body',
    ]);

    $response->assertSessionHasErrors('title');

    $this->assertDatabaseCount('notes', 0);
});

it('rejects a title longer than 255 characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => str_repeat('a', 256),
        'body' => 'Valid body',
    ]);

    $response->assertSessionHasErrors('title');

    $this->assertDatabaseCount('notes', 0);
});

it('requires the body to be a string when present', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Valid title',
        'body' => ['not', 'a', 'string'],
    ]);

    $response->assertSessionHasErrors('body');

    $this->assertDatabaseCount('notes', 0);
});

it('rejects a body longer than 10000 characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => 'Valid title',
        'body' => str_repeat('a', 10001),
    ]);

    $response->assertSessionHasErrors('body');

    $this->assertDatabaseCount('notes', 0);
});

it('accepts note fields at their maximum lengths', function () {
    $user = User::factory()->create();

    $title = str_repeat('a', 255);
    $body = str_repeat('b', 10000);

    $response = $this->actingAs($user)->post(route('notes.store'), [
        'title' => $title,
        'body' => $body,
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('notes', [
        'user_id' => $user->id,
        'title' => $title,
        'body' => $body,
    ]);
});

it('shows a note', function () {
    $user = User::factory()->create();

    $note = Note::factory()->for($user)->create([
        'title' => 'Visible title',
        'body' => 'Visible body',
    ]);

    $response = $this->actingAs($user)->get(route('notes.show', $note));

    $response
        ->assertOk()
        ->assertViewIs('notes.show')
        ->assertViewHas('note', fn (Note $viewNote) => $viewNote->is($note))
        ->assertSeeText('Visible title')
        ->assertSeeText('Visible body');
});

it('escapes note content when rendered', function () {
    $user = User::factory()->create();
    $unsafeBody = '<script>alert("stored xss")</script>';

    $note = Note::factory()->for($user)->create([
        'title' => 'Security test',
        'body' => $unsafeBody,
    ]);

    $response = $this->actingAs($user)->get(route('notes.show', $note));

    $response
        ->assertOk()
        ->assertSeeText($unsafeBody)
        ->assertDontSee($unsafeBody, false);
});

it('updates a note', function () {
    $user = User::factory()->create();

    $note = Note::factory()->for($user)->create([
        'title' => 'Old title',
        'body' => 'Old body',
    ]);

    $response = $this->actingAs($user)->put(
        route('notes.update', $note),
        [
            'title' => 'Updated title',
            'body' => 'Updated body',
        ],
    );

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'user_id' => $user->id,
        'title' => 'Updated title',
        'body' => 'Updated body',
    ]);
});

it('deletes a note and its stored files', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    $attachments = Attachment::factory()
        ->count(2)
        ->for($note)
        ->sequence(
            ['path' => "notes/{$note->id}/first.txt"],
            ['path' => "notes/{$note->id}/second.txt"],
        )
        ->create();

    foreach ($attachments as $attachment) {
        Storage::disk('s3')->put($attachment->path, 'private contents');
    }

    $response = $this->actingAs($user)->delete(
        route('notes.destroy', $note),
    );

    $response
        ->assertRedirect(route('notes.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('notes', [
        'id' => $note->id,
    ]);

    foreach ($attachments as $attachment) {
        $this->assertDatabaseMissing('attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('s3')->assertMissing($attachment->path);
    }
});
