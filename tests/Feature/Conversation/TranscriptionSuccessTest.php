<?php

use App\Contracts\TurnInterpreter;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('hides the transcript on the conversation when transcription completes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $voiceTurn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'Olá, mundo!',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::NotAboutTheBooks));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $voiceTurn->uuid)
        ->assertDontSee('Olá, mundo!')
        ->assertSee('Eu só falo dos seus compromissos e tarefas.')
        ->assertDontSee('Pensando...');
});
