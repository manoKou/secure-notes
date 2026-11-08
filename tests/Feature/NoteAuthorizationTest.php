<?php

use App\Models\Note;
use App\Models\User;

/**
 * Guests are sent to login. Foreign notes always return 404, not 403.
 */

it('redirects guests to login from every note route', function () {
    $note = Note::factory()->create();

    $responses = [
        $this->get(route('notes.index')),
        $this->get(route('notes.create')),
        $this->post(route('notes.store'), [
            'title' => 'Guest note',
            'body' => null,
        ]),
        $this->get(route('notes.show', $note)),
        $this->get(route('notes.edit', $note)),
        $this->put(route('notes.update', $note), [
            'title' => 'Guest update',
            'body' => null,
        ]),
        $this->delete(route('notes.destroy', $note)),
    ];

    foreach ($responses as $response) {
        $response->assertRedirect(route('login'));
    }

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
    ]);
});

it('returns 404 when viewing another users note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $this
        ->actingAs($otherUser)
        ->get(route('notes.show', $note))
        ->assertNotFound();
});

it('returns 404 when editing another users note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $this
        ->actingAs($otherUser)
        ->get(route('notes.edit', $note))
        ->assertNotFound();
});

it('returns 404 when updating another users note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $note = Note::factory()->for($owner)->create([
        'title' => 'Owners title',
        'body' => 'Owners body',
    ]);

    $this
        ->actingAs($otherUser)
        ->put(route('notes.update', $note), [
            'title' => 'Unauthorized update',
            'body' => 'This must not be saved.',
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'user_id' => $owner->id,
        'title' => 'Owners title',
        'body' => 'Owners body',
    ]);
});

it('returns 404 before validating an update to another users note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $this
        ->actingAs($otherUser)
        ->put(route('notes.update', $note), [])
        ->assertNotFound();
});

it('returns 404 when deleting another users note', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->for($owner)->create();

    $this
        ->actingAs($otherUser)
        ->delete(route('notes.destroy', $note))
        ->assertNotFound();

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'user_id' => $owner->id,
    ]);
});
