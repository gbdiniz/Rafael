<?php

use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

it('shows the transcript on the conversation when transcription completes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $voiceTurn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'Olá, mundo!',
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $voiceTurn->uuid)
        ->assertSee('Olá, mundo!')
        ->assertDontSee('Transcrevendo...');
});
