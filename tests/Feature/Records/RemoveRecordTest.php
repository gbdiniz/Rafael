<?php

use App\Contracts\TurnInterpreter;
use App\Enums\MutationAction;
use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\mock;
use function Pest\Laravel\travelTo;

it('soft-deletes the named record after yes', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'remove a consulta',
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
                action: MutationAction::Remove,
                title: 'Consulta',
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    assertSoftDeleted($record);
});

it('does not delete the record when the answer is no', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'remove a consulta',
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
                action: MutationAction::Remove,
                title: 'Consulta',
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::No),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $no->uuid);

    expect($record->fresh()->trashed())->toBeFalse();
});

it('omits a removed appointment from the missed briefing', function () {
    travelTo(Carbon::parse('2026-10-06 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Exame não feito',
        'scheduled_at' => Carbon::parse('2026-10-05 09:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'remove o exame',
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
                action: MutationAction::Remove,
                title: 'Exame não feito',
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::Yes),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('Exame não feito')
        ->assertSee('Nada nos livros por hoje.');
});

it('omits a removed record from answers', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $record = Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar',
    ]);

    $describe = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'remove ligar',
    ]);
    $yes = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'sim',
    ]);
    $ask = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'minhas tarefas',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->times(3)
        ->andReturn(
            new Interpretation(
                TurnIntent::MutationDraft,
                action: MutationAction::Remove,
                title: 'Ligar',
                recordId: $record->id,
            ),
            new Interpretation(TurnIntent::Yes),
            new Interpretation(TurnIntent::Question, QuestionTopic::Tasks),
        );

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $describe->uuid)
        ->call('trackVoiceTurn', $yes->uuid)
        ->call('trackVoiceTurn', $ask->uuid)
        ->assertSee('Nenhuma tarefa nos livros.')
        ->assertDontSee('Tarefas: Ligar.');
});
