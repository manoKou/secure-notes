<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NoteController;

// No marketing page: "/" goes to the notes (guests are sent on to login).
Route::redirect('/', '/notes')->name('home');

// The kit's login/register code redirects to route('dashboard'); keep the
// name working, but our app's home is the notes list.
Route::redirect('dashboard', '/notes')->middleware('auth')->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Notes: the 7 CRUD routes (notes.index, notes.create, notes.store, ...).
    Route::resource('notes', NoteController::class);

    // Attachments are nested under their note. scopeBindings() makes Laravel
    // look the attachment up through $note->attachments(), so an attachment
    // of another note returns 404.
    Route::scopeBindings()->group(function () {
        Route::post('notes/{note}/attachments', [AttachmentController::class, 'store'])
            ->name('notes.attachments.store');
        Route::get('notes/{note}/attachments/{attachment}', [AttachmentController::class, 'download'])
            ->name('notes.attachments.download');
        Route::delete('notes/{note}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
            ->name('notes.attachments.destroy');
    });
});

require __DIR__.'/auth.php';
