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

it('updates title, time, and kind on the same record after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
        'scheduled_at' => Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc(),
        'last_transcript' => 'consulta original',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'muda a consulta para a tarefa dentista amanhã às 9',
    ]);
    $yes = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'sim',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->twice()
        ->andReturn(
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Change,
                kind: RecordKind::Task,
                title: 'Dentista',
                scheduledAt: Carbon::parse('2026-10-08 09:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $record->refresh();

    expect(Record::query()->count())->toBe(1);
    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->title)->toBe('Dentista');
    expect($record->scheduled_at?->equalTo(Carbon::parse('2026-10-08 09:00:00', 'America/Sao_Paulo')->utc()))->toBeTrue();
    expect($record->last_transcript)->toBe('muda a consulta para a tarefa dentista amanhã às 9');
});

it('keeps the previous title and transcript when the answer is no', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
        'last_transcript' => 'consulta original',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'muda para dentista',
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

it('keeps the same scheduled_at when the type flips', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $scheduledAt = Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc();
    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
        'scheduled_at' => $scheduledAt,
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'vira tarefa',
    ]);
    $yes = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'sim',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->twice()
        ->andReturn(
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Change,
                kind: RecordKind::Task,
                title: 'Consulta',
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $record->refresh();

    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->scheduled_at?->equalTo($scheduledAt))->toBeTrue();
});

it('does not flip a task that has no due date before a start time is given', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $record = Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'vira compromisso',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(
            TurnIntent::MutationDraft,
            action: MutationAction::Change,
            kind: RecordKind::Appointment,
            title: 'Ligar',
            recordId: $record->id,
        ));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('preciso do horário');

    $record->refresh();

    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->scheduled_at)->toBeNull();
});
