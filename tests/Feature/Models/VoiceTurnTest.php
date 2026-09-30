<?php

use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use App\VoiceTurnStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

use function Pest\Laravel\assertModelExists;

it('stores an uploaded turn with a unique uuid for its user', function () {
    $user = User::factory()->create();
    $uuid = (string) Str::uuid();

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'uuid' => $uuid,
    ]);

    expect($voiceTurn->uuid)->toBe($uuid);
    expect($voiceTurn->user_id)->toBe($user->id);
    expect($voiceTurn->status->value)->toBe('uploaded');
});

it('rejects a second turn that reuses the same uuid', function () {
    $user = User::factory()->create();
    $uuid = (string) Str::uuid();

    VoiceTurn::factory()->for($user)->create(['uuid' => $uuid]);

    expect(fn () => VoiceTurn::factory()->for($user)->create(['uuid' => $uuid]))
        ->toThrow(QueryException::class);
});

it('clears the record link and keeps the transcript when the voice turn is deleted', function () {
    $user = User::factory()->create();
    $voiceTurn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'mudar o horário da consulta',
    ]);
    $record = Record::factory()->for($user)->create([
        'last_voice_turn_id' => $voiceTurn->id,
        'last_transcript' => 'mudar o horário da consulta',
    ]);

    $voiceTurn->delete();

    $record->refresh();

    assertModelExists($record);
    expect($record->last_transcript)->toBe('mudar o horário da consulta');
    expect($record->last_voice_turn_id)->toBeNull();
});

it('rejects a voice turn whose user does not exist', function () {
    $voiceTurn = new VoiceTurn([
        'uuid' => (string) Str::uuid(),
        'status' => VoiceTurnStatus::Uploaded,
        'locale' => 'pt-BR',
    ]);
    $voiceTurn->user_id = 999_999;

    expect(fn () => $voiceTurn->save())->toThrow(QueryException::class);

    expect(VoiceTurn::query()->count())->toBe(0);
});
