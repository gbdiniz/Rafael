<?php

use App\Contracts\TurnInterpreter;
use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\mock;
use function Pest\Laravel\travelTo;

it('renders the stored tasks when asked about tasks', function () {
    travelTo(Carbon::parse('2026-10-06 15:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    Record::factory()->for($user)->task()->create([
        'title' => 'Pagar o boleto',
        'scheduled_at' => Carbon::parse('2026-10-07 09:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'quais são minhas tarefas',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Tasks));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Tarefas: Ligar para o médico, Pagar o boleto (prazo 07/10/2026 09:00).')
        ->assertDontSee('quais são minhas tarefas');

    expect($turn->fresh()->consumed_at)->not->toBeNull();
});

it('omits a soft-deleted task from the answer', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Tarefa ativa',
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Tarefa removida',
    ])->delete();

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'minhas tarefas',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Tasks));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Tarefas: Tarefa ativa.')
        ->assertDontSee('Tarefa removida');
});

it('does not create a record when answering a task question', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'minhas tarefas',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Tasks));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid);

    expect(Record::query()->count())->toBe(1);
});

it('still omits tasks from the return-visit briefing', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertDontSee('Ligar para o médico');
});

it('renders the stored tasks then appointments when asked about both', function () {
    travelTo(Carbon::parse('2026-10-06 15:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta futura',
        'scheduled_at' => Carbon::parse('2026-10-08 14:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'o que eu tenho',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Both));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Tarefas: Ligar para o médico. Compromissos: Consulta futura (08/10/2026 14:00).');
});
