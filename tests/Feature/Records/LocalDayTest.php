<?php

use App\Models\Record;
use App\Models\User;
use App\Records\LocalDay;
use Illuminate\Support\Carbon;

use function Pest\Laravel\travelTo;

it('stores a date-only due as midnight in America/Sao_Paulo', function () {
    travelTo(Carbon::parse('2026-09-15 12:00:00', 'UTC'));

    $utc = LocalDay::forTimezone('America/Sao_Paulo')->dateOnlyToUtc('2026-09-15');

    expect($utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-15 03:00:00');
});

it('counts an appointment earlier today as today', function () {
    travelTo(Carbon::parse('2026-09-30 15:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $scheduledAt = Carbon::parse('2026-09-30 09:00:00', 'America/Sao_Paulo')->utc();

    Record::factory()->for($user)->appointment()->create([
        'scheduled_at' => $scheduledAt,
    ]);

    $todayAppointments = Record::query()
        ->where('user_id', $user->id)
        ->onTheBooks()
        ->scheduledOnLocalDay($user->timezone, now())
        ->orderBy('scheduled_at')
        ->orderBy('id')
        ->get();

    expect($todayAppointments)->toHaveCount(1);
});

it('counts an appointment before local midnight as missed', function () {
    travelTo(Carbon::parse('2026-09-30 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $scheduledAt = Carbon::parse('2026-09-29 23:00:00', 'America/Sao_Paulo')->utc();

    Record::factory()->for($user)->appointment()->create([
        'scheduled_at' => $scheduledAt,
    ]);

    $missedAppointments = Record::query()
        ->where('user_id', $user->id)
        ->onTheBooks()
        ->scheduledBeforeLocalDay($user->timezone, now())
        ->orderBy('scheduled_at')
        ->orderBy('id')
        ->get();

    expect($missedAppointments)->toHaveCount(1);
});
