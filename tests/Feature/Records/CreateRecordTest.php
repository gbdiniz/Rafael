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

it('creates an appointment with a title and a start time after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca consulta amanhã às 14',
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
                action: MutationAction::Create,
                kind: RecordKind::Appointment,
                title: 'Consulta',
                scheduledAt: Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $record = Record::query()->first();

    expect(Record::query()->count())->toBe(1);
    expect($record->kind)->toBe(RecordKind::Appointment);
    expect($record->title)->toBe('Consulta');
    expect($record->scheduled_at?->equalTo(Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc()))->toBeTrue();
    expect($record->last_voice_turn_id)->toBe($describe->id);
});

it('does not create an appointment that has no start time', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca uma consulta',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(
            TurnIntent::MutationDraft,
            action: MutationAction::Create,
            kind: RecordKind::Appointment,
            title: 'Consulta',
        ));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('preciso do horário');

    expect(Record::query()->count())->toBe(0);
});

it('creates a task with no due date after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'anota a tarefa ligar',
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
                action: MutationAction::Create,
                kind: RecordKind::Task,
                title: 'Ligar',
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $record = Record::query()->first();

    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->title)->toBe('Ligar');
    expect($record->scheduled_at)->toBeNull();
});

it('creates a task with a due date after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'anota a tarefa pagar até amanhã às 9',
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
                action: MutationAction::Create,
                kind: RecordKind::Task,
                title: 'Pagar',
                scheduledAt: Carbon::parse('2026-10-07 09:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    $record = Record::query()->first();

    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->scheduled_at?->equalTo(Carbon::parse('2026-10-07 09:00:00', 'America/Sao_Paulo')->utc()))->toBeTrue();
});

it('renders the title, time, and type before yes', function () {
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
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Consulta')
        ->assertSee('compromisso')
        ->assertSee('07/10/2026 14:00');

    expect(Record::query()->count())->toBe(0);
});

it('stores the describing utterance as last_transcript, not the yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca consulta amanhã às 14',
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
                action: MutationAction::Create,
                kind: RecordKind::Appointment,
                title: 'Consulta',
                scheduledAt: Carbon::parse('2026-10-07 14:00:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    expect(Record::query()->first()->last_transcript)->toBe('marca consulta amanhã às 14');
});
