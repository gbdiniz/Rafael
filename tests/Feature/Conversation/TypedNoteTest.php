<?php

use App\Models\User;
use Livewire\Livewire;

it('renders no text input for a note on the conversation', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('<textarea', escape: false)
        ->assertDontSee('type="text"', escape: false)
        ->assertDontSee('type="search"', escape: false);
});

it('returns not found for a typed-note url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/notes')
        ->assertNotFound();
});
