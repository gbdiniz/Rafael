<?php

use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\travelTo;

it('includes every appointment that starts today on a return visit', function () {
    travelTo(Carbon::parse('2026-09-30 15:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Reunião da tarde',
        'scheduled_at' => Carbon::parse('2026-09-30 16:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Compromissos de hoje: Reunião da tarde.');
});

it('includes an appointment that started earlier today', function () {
    travelTo(Carbon::parse('2026-09-30 15:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta da manhã',
        'scheduled_at' => Carbon::parse('2026-09-30 09:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Compromissos de hoje: Consulta da manhã.');
});

it('includes missed appointments on a return visit', function () {
    travelTo(Carbon::parse('2026-09-30 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Exame não feito',
        'scheduled_at' => Carbon::parse('2026-09-29 23:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Compromissos pendentes: Exame não feito.');
});

it('omits future appointments from the return visit', function () {
    travelTo(Carbon::parse('2026-09-30 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta de amanhã',
        'scheduled_at' => Carbon::parse('2026-10-01 09:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Nada nos livros por hoje.')
        ->assertDontSee('Consulta de amanhã');
});

it('omits tasks from the return visit', function () {
    travelTo(Carbon::parse('2026-09-30 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Nada nos livros por hoje.')
        ->assertDontSee('Ligar para o médico');
});

it('omits a soft-deleted appointment from the return visit', function () {
    travelTo(Carbon::parse('2026-09-30 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Consulta removida',
        'scheduled_at' => Carbon::parse('2026-09-30 09:00:00', 'America/Sao_Paulo')->utc(),
    ])->delete();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Nada nos livros por hoje.')
        ->assertDontSee('Consulta removida');
});

it('uses America/Sao_Paulo when that is the user timezone', function () {
    travelTo(Carbon::parse('2026-09-30 03:00:00', 'UTC'));

    $user = User::factory()->create([
        'conversation_opened_at' => now(),
        'timezone' => 'America/Sao_Paulo',
    ]);

    Record::factory()->for($user)->appointment()->create([
        'title' => 'Compromisso no fuso local',
        'scheduled_at' => Carbon::parse('2026-09-29 23:00:00', 'America/Sao_Paulo')->utc(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('Compromissos pendentes: Compromisso no fuso local.');
});
