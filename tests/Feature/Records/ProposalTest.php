<?php

use App\Contracts\TurnInterpreter;
use App\Enums\MutationAction;
use App\Enums\RecordKind;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('does not insert a record before yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca consulta amanhã às 14',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(
            TurnIntent::MutationDraft,
            action: MutationAction::Create,
            kind: RecordKind::Appointment,
            title: 'Consulta',
            scheduledAt: Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
        ));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid);

    expect(Record::query()->count())->toBe(0);
});

it('leaves the existing record unchanged when the answer is no', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
        'last_transcript' => 'consulta original',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'muda o título da consulta para Dentista',
    ]);
    $no = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'não',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->twice()
        ->andReturn(
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Change,
                kind: RecordKind::Appointment,
                title: 'Dentista',
                scheduledAt: $record->scheduled_at->toIso8601String(),
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::No),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $no->uuid);

    $record->refresh();

    expect($record->title)->toBe('Consulta');
    expect($record->last_transcript)->toBe('consulta original');
});

it('leaves the record unchanged when the proposal is left without yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca consulta amanhã às 14',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(
            TurnIntent::MutationDraft,
            action: MutationAction::Create,
            kind: RecordKind::Appointment,
            title: 'Consulta',
            scheduledAt: Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
        ));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid);

    expect(Record::query()->count())->toBe(0);
});
