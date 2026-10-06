<?php

use App\Enums\RecordKind;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertModelExists;

it('refuses to persist an appointment without a scheduled time', function () {
    $user = User::factory()->create();

    expect(fn () => Record::query()->create([
        'user_id' => $user->id,
        'kind' => RecordKind::Appointment,
        'title' => 'Consulta',
        'scheduled_at' => null,
        'last_transcript' => 'marcar consulta amanhã',
    ]))->toThrow(QueryException::class);
});

it('persists a task that has no due time', function () {
    $user = User::factory()->create();

    $record = Record::factory()->for($user)->withoutDueDate()->create([
        'title' => 'Ligar para o médico',
        'last_transcript' => 'lembrar de ligar',
    ]);

    expect($record->kind)->toBe(RecordKind::Task);
    expect($record->scheduled_at)->toBeNull();
});

it('allows two records to share a title', function () {
    $user = User::factory()->create();

    Record::factory()->for($user)->create(['title' => 'Consulta']);
    Record::factory()->for($user)->create(['title' => 'Consulta']);

    expect(Record::query()->where('title', 'Consulta')->count())->toBe(2);
});

it('keeps the record transcript and clears the turn link when the voice turn is deleted', function () {
    $user = User::factory()->create();
    $voiceTurn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'cancelar a reunião',
    ]);
    $record = Record::factory()->for($user)->create([
        'last_voice_turn_id' => $voiceTurn->id,
        'last_transcript' => 'cancelar a reunião',
    ]);

    $voiceTurn->delete();

    $record->refresh();

    assertModelExists($record);
    expect($record->last_transcript)->toBe('cancelar a reunião');
    expect($record->last_voice_turn_id)->toBeNull();
});

it('hides a soft-deleted record from the default query', function () {
    $user = User::factory()->create();
    $record = Record::factory()->for($user)->create();

    $record->delete();

    expect(Record::query()->find($record->id))->toBeNull();
    expect(Record::withTrashed()->find($record->id))->not->toBeNull();
});

it('keeps the user and the voice turn when the record is force-deleted', function () {
    $user = User::factory()->create();
    $voiceTurn = VoiceTurn::factory()->for($user)->create();
    $record = Record::factory()->for($user)->create([
        'last_voice_turn_id' => $voiceTurn->id,
    ]);

    $record->forceDelete();

    assertModelExists($user);
    assertModelExists($voiceTurn);
});

it('rejects a record user id that does not exist', function () {
    $record = new Record([
        'kind' => RecordKind::Task,
        'title' => 'Ligar para o médico',
        'last_transcript' => 'lembrar de ligar',
    ]);
    $record->user_id = 999_999;

    expect(fn () => $record->save())->toThrow(QueryException::class);

    expect(Record::query()->count())->toBe(0);
});

it('rejects a last voice turn id that does not exist', function () {
    $user = User::factory()->create();

    expect(fn () => Record::factory()->for($user)->create([
        'last_voice_turn_id' => 999_999,
    ]))->toThrow(QueryException::class);
});

it('follows the voice turn id when that turn id changes', function () {
    $user = User::factory()->create();
    $voiceTurn = VoiceTurn::factory()->for($user)->create();
    $record = Record::factory()->for($user)->create([
        'last_voice_turn_id' => $voiceTurn->id,
    ]);
    $newVoiceTurnId = VoiceTurn::query()->max('id') + 100;

    DB::update('UPDATE voice_turns SET id = ? WHERE id = ?', [$newVoiceTurnId, $voiceTurn->id]);

    expect($record->fresh()->last_voice_turn_id)->toBe($newVoiceTurnId);
});
