<?php

use App\Contracts\TurnInterpreter;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('does not change records when the question is off the books', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $record = Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta existente',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'qual a capital da França',
    ]);

    $originalTitle = $record->title;
    $originalTranscript = $record->last_transcript;

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::NotAboutTheBooks));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid);

    $record->refresh();

    expect($record->title)->toBe($originalTitle);
    expect($record->last_transcript)->toBe($originalTranscript);
    expect(Record::query()->count())->toBe(1);
});

it('renders a Portuguese refusal when the question is off the books', function () {
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

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Eu só falo dos seus compromissos e tarefas.')
        ->assertDontSee('qual a capital da França');

    expect($turn->fresh()->consumed_at)->not->toBeNull();
});

it('makes no http call for an off-books question', function () {
    Http::preventStrayRequests();

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

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid);
});

it('asks whether the question is about tasks or appointments when the topic is missing', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'o que eu tenho',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Não entendi se você quer as tarefas ou os compromissos.');

    expect($turn->fresh()->consumed_at)->not->toBeNull();
    expect(Record::query()->count())->toBe(0);
});

it('leaves the briefing unchanged when the interpreter returns a mutation draft', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'marca uma consulta amanhã',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::MutationDraft));

    $component = Livewire::actingAs($user)->test('pages::conversation');
    $message = $component->get('message');

    $component
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSet('message', $message);

    expect($turn->fresh()->consumed_at)->not->toBeNull();
    expect(Record::query()->count())->toBe(0);
});

it('leaves the briefing unchanged when the interpreter returns yes or no', function (TurnIntent $intent) {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => $intent === TurnIntent::Yes ? 'sim' : 'não',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation($intent));

    $component = Livewire::actingAs($user)->test('pages::conversation');
    $message = $component->get('message');

    $component
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSet('message', $message);

    expect($turn->fresh()->consumed_at)->not->toBeNull();
    expect(Record::query()->count())->toBe(0);
})->with([
    TurnIntent::Yes,
    TurnIntent::No,
]);
