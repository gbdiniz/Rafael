<?php

use App\Exceptions\TransientTranscriptionException;
use App\Models\User;
use App\Models\VoiceTurn;
use App\Services\Transcription\HttpTranscriber;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('logs how long a completed transcription took', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    config([
        'rafael.transcriber.url' => 'http://127.0.0.1:9000/asr',
        'rafael.transcriber.model' => 'base',
    ]);

    $logged = [];

    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $user = User::factory()->create();
    $path = 'voice-turns/timing.webm';
    Storage::disk('local')->put($path, str_repeat('a', 512));

    $turn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
        'locale' => 'pt-BR',
    ]);

    Http::fake([
        'http://127.0.0.1:9000/asr*' => Http::response(['text' => 'preciso remarcar a consulta']),
    ]);

    $transcript = (new HttpTranscriber)->transcribe($turn);

    expect($transcript)->toBe('preciso remarcar a consulta');

    $completion = collect($logged)->first(
        fn (MessageLogged $message) => $message->message === 'Transcription completed.',
    );

    expect($completion)->toBeInstanceOf(MessageLogged::class);
    expect($completion->level)->toBe('info');
    expect($completion->context)->toMatchArray([
        'voice_turn_id' => $turn->id,
        'model' => 'base',
        'status' => 200,
        'audio_bytes' => 512,
    ]);
    expect($completion->context['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($completion->context)->not->toHaveKey('message');
    expect(json_encode($completion->context))->not->toContain('preciso remarcar a consulta');
});

it('logs how long a failed transcription took', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    config([
        'rafael.transcriber.url' => 'http://127.0.0.1:9000/asr',
        'rafael.transcriber.model' => 'base',
    ]);

    $logged = [];

    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $user = User::factory()->create();
    $path = 'voice-turns/timing.webm';
    Storage::disk('local')->put($path, str_repeat('a', 512));

    $turn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
    ]);

    Http::fake([
        'http://127.0.0.1:9000/asr*' => Http::response([
            'error' => [
                'message' => 'overloaded',
                'code' => 'overloaded',
            ],
        ], 503),
    ]);

    expect(fn () => (new HttpTranscriber)->transcribe($turn))
        ->toThrow(TransientTranscriptionException::class);

    $failure = collect($logged)->first(
        fn (MessageLogged $message) => $message->message === 'Transcription request failed.',
    );

    expect($failure)->toBeInstanceOf(MessageLogged::class);
    expect($failure->level)->toBe('warning');
    expect($failure->context)->toMatchArray([
        'voice_turn_id' => $turn->id,
        'model' => 'base',
        'status' => 503,
        'code' => 'overloaded',
        'message' => 'overloaded',
    ]);
    expect($failure->context['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
});
