<?php

use App\Enums\VoiceTurnStatus;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\travelTo;

it('deletes turns older than seven days in every status', function () {
    travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

    $user = User::factory()->create();

    foreach (VoiceTurnStatus::cases() as $status) {
        VoiceTurn::factory()->for($user)->create([
            'status' => $status,
            'created_at' => now()->subDays(8),
        ]);
    }

    $this->artisan('voice-turns:prune')->assertSuccessful();

    expect(VoiceTurn::query()->count())->toBe(0);
});

it('keeps the record transcript and clears the turn link when pruning', function () {
    travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'created_at' => now()->subDays(8),
        'transcript' => 'marca uma consulta',
    ]);
    $record = Record::factory()->for($user)->appointment()->create([
        'last_voice_turn_id' => $turn->id,
        'last_transcript' => 'marca uma consulta',
    ]);

    $this->artisan('voice-turns:prune')->assertSuccessful();

    $record->refresh();

    assertModelExists($record);
    expect($record->last_transcript)->toBe('marca uma consulta');
    expect($record->last_voice_turn_id)->toBeNull();
});

it('keeps turns newer than seven days', function () {
    travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'created_at' => now()->subDays(6),
    ]);

    $this->artisan('voice-turns:prune')->assertSuccessful();

    expect(VoiceTurn::query()->count())->toBe(1);
    expect($turn->fresh())->not->toBeNull();
});

it('schedules the prune daily without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduled) => str_contains((string) $scheduled->command, 'voice-turns:prune'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('0 0 * * *');
    expect($event->withoutOverlapping)->toBeTrue();
});
