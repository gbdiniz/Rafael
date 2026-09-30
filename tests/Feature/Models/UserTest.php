<?php

use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('persists America/Sao_Paulo as the timezone on a new user', function () {
    $user = User::factory()->create();

    expect($user->timezone)->toBe('America/Sao_Paulo');
});

it('leaves conversation_opened_at null on a new user', function () {
    $user = User::factory()->create();

    expect($user->conversation_opened_at)->toBeNull();
});

it('refuses to delete a user who still owns a record', function () {
    $user = User::factory()->create();
    Record::factory()->for($user)->create();

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::query()->find($user->id))->not->toBeNull();
});

it('refuses to delete a user who still owns a voice turn', function () {
    $user = User::factory()->create();
    VoiceTurn::factory()->for($user)->create();

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::query()->find($user->id))->not->toBeNull();
});

it('refuses to delete a user whose only record is soft-deleted', function () {
    $user = User::factory()->create();
    Record::factory()->for($user)->create()->delete();

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::query()->find($user->id))->not->toBeNull();
});

it('updates record and voice turn user ids when the user id changes', function () {
    $user = User::factory()->create();
    $record = Record::factory()->for($user)->create();
    $voiceTurn = VoiceTurn::factory()->for($user)->create();
    $newUserId = User::query()->max('id') + 100;

    DB::update('UPDATE users SET id = ? WHERE id = ?', [$newUserId, $user->id]);

    expect($record->fresh()->user_id)->toBe($newUserId);
    expect($voiceTurn->fresh()->user_id)->toBe($newUserId);
});
