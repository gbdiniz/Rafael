<?php

use App\Contracts\SpeechPhraser;
use App\Contracts\TurnInterpreter;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('shows the speech string after a voice turn', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'qual a capital da França',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::NotAboutTheBooks));

    mock(SpeechPhraser::class)
        ->shouldReceive('phrase')
        ->once()
        ->with('Eu só falo dos seus compromissos e tarefas.')
        ->andReturn('Eu só posso falar dos seus compromissos e tarefas.');

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Eu só posso falar dos seus compromissos e tarefas.')
        ->assertDontSee('Eu só falo dos seus compromissos e tarefas.');
});

it('keeps the php line when the speech completion fails', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'qual a capital da França',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::NotAboutTheBooks));

    mock(SpeechPhraser::class)
        ->shouldReceive('phrase')
        ->once()
        ->andThrow(new RuntimeException('A fala falhou.'));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Eu só falo dos seus compromissos e tarefas.');
});

it('does not phrase the opening line', function () {
    $user = User::factory()->create();

    mock(SpeechPhraser::class)->shouldNotReceive('phrase');

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Olá.');
});
