<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('deleting an account removes its attachment files but not other users files', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $notes = Note::factory()
        ->count(2)
        ->for($user)
        ->create();

    $attachments = $notes->map(function (Note $note) {
        $attachment = Attachment::factory()->for($note)->create([
            'disk' => 's3',
            'path' => "notes/{$note->id}/private.txt",
        ]);

        Storage::disk('s3')->put(
            $attachment->path,
            'private contents',
        );

        return $attachment;
    });

    $otherNote = Note::factory()
        ->for($otherUser)
        ->create();

    $otherAttachment = Attachment::factory()
        ->for($otherNote)
        ->create([
            'disk' => 's3',
            'path' => "notes/{$otherNote->id}/other-users-file.txt",
        ]);

    Storage::disk('s3')->put(
        $otherAttachment->path,
        'other users contents',
    );

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);

    foreach ($notes as $note) {
        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
        ]);
    }

    foreach ($attachments as $attachment) {
        $this->assertDatabaseMissing('attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('s3')->assertMissing($attachment->path);
    }

    $this->assertDatabaseHas('users', [
        'id' => $otherUser->id,
    ]);

    $this->assertDatabaseHas('notes', [
        'id' => $otherNote->id,
        'user_id' => $otherUser->id,
    ]);

    $this->assertDatabaseHas('attachments', [
        'id' => $otherAttachment->id,
        'note_id' => $otherNote->id,
    ]);

    Storage::disk('s3')->assertExists($otherAttachment->path);
});
