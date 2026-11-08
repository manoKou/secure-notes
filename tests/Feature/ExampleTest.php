<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home redirects to the notes', function () {
    $this->get('/')->assertRedirect('/notes');
});
