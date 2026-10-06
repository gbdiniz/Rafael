<?php

use App\Contracts\TurnInterpreter;
use App\Enums\MutationAction;
use App\Enums\RecordKind;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('names both matches and changes neither when two titles match', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $morning = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta da manhã',
    ]);
    $afternoon = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta da tarde',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'muda a consulta',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(
            TurnIntent::MutationDraft,
            action: MutationAction::Change,
            kind: RecordKind::Appointment,
            title: 'Consulta',
        ));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Consulta da manhã')
        ->assertSee('Consulta da tarde')
        ->assertSeeHtml('wire:key="match-'.$morning->id.'"')
        ->assertSeeHtml('wire:key="match-'.$afternoon->id.'"');

    expect($morning->fresh()->title)->toBe('Consulta da manhã');
    expect($afternoon->fresh()->title)->toBe('Consulta da tarde');
});

it('updates only the chosen record after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $morning = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta da manhã',
        'last_transcript' => 'manhã original',
    ]);
    $afternoon = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta da tarde',
        'last_transcript' => 'tarde original',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'muda a consulta',
    ]);
    $choice = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'a da manhã',
    ]);
    $yes = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'sim',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->times(3)
        ->andReturn(
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Change,
                kind: RecordKind::Appointment,
                title: 'Consulta',
            ),
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Change,
                title: 'Consulta da manhã',
                recordId: $morning->id,
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $choice->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $morning->refresh();
    $afternoon->refresh();

    expect($morning->title)->toBe('Consulta da manhã');
    expect($morning->last_transcript)->toBe('muda a consulta');
    expect($afternoon->title)->toBe('Consulta da tarde');
    expect($afternoon->last_transcript)->toBe('tarde original');
});
