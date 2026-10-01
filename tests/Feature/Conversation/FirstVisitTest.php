<?php

use App\Models\Record;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertModelExists;

it('renders a greeting and no records on the first open', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => null,
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta oculta na saudação',
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Olá.')
        ->assertDontSee('Consulta oculta na saudação');
});

it('does not render a product tour on the first open', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('Tour')
        ->assertDontSee('Passo a passo')
        ->assertDontSee('Como usar');
});

it('sets conversation_opened_at on the first open', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => null,
    ]);

    Livewire::actingAs($user)->test('pages::conversation');

    assertModelExists($user);
    expect($user->fresh()->conversation_opened_at)->not->toBeNull();
});

it('does not render the first-visit greeting on the second open', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('Olá.')
        ->assertSee('Nada nos livros por hoje.');
});
