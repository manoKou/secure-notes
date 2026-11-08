<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('home redirects to the notes', function () {
    $this->get('/')->assertRedirect('/notes');
});