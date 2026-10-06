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

it('includes a future appointment when asked about appointments', function () {
    travelTo(Carbon::parse('2026-10-06 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta futura',
        'scheduled_at' => Carbon::parse('2026-10-08 14:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'quais são meus compromissos',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Appointments));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Compromissos: Consulta futura (08/10/2026 14:00).')
        ->assertDontSee('quais são meus compromissos');
});

it('omits a soft-deleted appointment from the answer', function () {
    travelTo(Carbon::parse('2026-10-06 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta ativa',
        'scheduled_at' => Carbon::parse('2026-10-07 09:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta removida',
        'scheduled_at' => Carbon::parse('2026-10-07 11:00:00', 'America/Sao_Paulo')->utc(),
    ])->delete();

    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'meus compromissos',
    ]);

    mock(TurnInterpreter::class)
        ->shouldReceive('interpret')
        ->once()
        ->andReturn(new Interpretation(TurnIntent::Question, QuestionTopic::Appointments));

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->call('trackVoiceTurn', $turn->uuid)
        ->assertSee('Compromissos: Consulta ativa (07/10/2026 09:00).')
        ->assertDontSee('Consulta removida');
});
