<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NoteController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
