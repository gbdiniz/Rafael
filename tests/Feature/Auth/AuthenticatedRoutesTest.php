<?php

use App\Models\User;

it('redirects a guest to login when opening the conversation', function () {
    $this->get(route('conversation'))
        ->assertRedirect(route('login'));
});

it('redirects a guest to login when uploading audio', function () {
    $this->post(route('voice-turns.store'))
        ->assertRedirect(route('login'));
});

it('opens the conversation for a signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('conversation'))
        ->assertOk()
        ->assertSee('Conversa');
});
