<?php

use App\Models\User;
use Livewire\Livewire;

it('renders the conversation instead of the welcome page for a signed-in user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('Let\'s get started')
        ->assertSee('Olá.');
});

it('does not render a record list on the conversation', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('<ul')
        ->assertDontSee('role="list"');
});

it('renders the conversation copy in Portuguese', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Nada nos livros por hoje.');
});
