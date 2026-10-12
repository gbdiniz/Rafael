<?php

use App\Contracts\SpeechSynthesizer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('renders the reply as text when speech is blocked', function () {
    mock(SpeechSynthesizer::class)
        ->shouldReceive('speak')
        ->once()
        ->andReturn(false);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Olá.')
        ->assertSee('Não foi possível falar.');
});

it('does not store an audio file when speaking a reply', function () {
    Storage::fake('local');
    Storage::fake('public');

    mock(SpeechSynthesizer::class)
        ->shouldReceive('speak')
        ->once()
        ->andReturn(true);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::conversation');

    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
