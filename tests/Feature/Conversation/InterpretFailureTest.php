<?php

use App\Contracts\TurnInterpreter;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Livewire\Livewire;
use RuntimeException;

use function Pest\Laravel\mock;

it('stores an interpretation error and does not consume the turn', function () {
    $user = User::factory()->create([
        'conversation_opened_at' => now(),
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'minhas tarefas',
        'error_message' => null,
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andThrow(new RuntimeException('provider down'));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Não foi possível entender o que você disse.')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Não foi possível entender o que você disse.');

    $turn->refresh();

    expect($turn->error_message)->toBe('Não foi possível entender o que você disse.');
    expect($turn->consumed_at)->toBeNull();
    expect(Record::query()->count())->toBe(1);
});
