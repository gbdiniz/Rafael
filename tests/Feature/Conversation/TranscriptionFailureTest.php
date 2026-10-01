<?php

use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

it('shows the turn error message on the conversation', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $voiceTurn = VoiceTurn::factory()->for($user)->failed()->create([
        'error_message' => 'Não foi possível transcrever.',
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $voiceTurn->uuid)
        ->assertSee('Não foi possível transcrever.');
});
