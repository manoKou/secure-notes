<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users are sent from the dashboard to their notes', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertRedirect('/notes');
});