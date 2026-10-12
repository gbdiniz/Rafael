<?php

use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

it('renders how to allow the microphone when permission is denied', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Sem microfone')
        ->assertSee('Permita o microfone para falar com Rafael.');
});

it('does not create a turn when the microphone is denied', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::conversation');

    expect(VoiceTurn::query()->count())->toBe(0);
});
