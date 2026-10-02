<?php

use App\Contracts\Transcriber;
use App\Exceptions\PermanentTranscriptionException;
use App\Jobs\TranscribeVoiceTurn;
use App\Models\User;
use App\Models\VoiceTurn;
use App\VoiceTurnStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('stores the transcript and deletes the file when transcription completes', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'voice-turns/test.webm';
    Storage::disk('local')->put($path, 'audio-bytes');

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
    ]);

    mock(Transcriber::class)
        ->shouldReceive('transcribe')
        ->once()
        ->withArgs(fn (VoiceTurn $turn) => $turn->is($voiceTurn))
        ->andReturn('preciso remarcar a consulta');

    (new TranscribeVoiceTurn($voiceTurn))->handle(app(Transcriber::class));

    $voiceTurn->refresh();

    expect($voiceTurn->status)->toBe(VoiceTurnStatus::Completed);
    expect($voiceTurn->transcript)->toBe('preciso remarcar a consulta');
    expect($voiceTurn->audio_path)->toBeNull();
    expect($voiceTurn->disk)->toBeNull();
    expect($voiceTurn->audio_deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('does not call an unfaked host during transcription', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    config([
        'rafael.transcriber.api_key' => 'test-transcriber-key',
        'rafael.transcriber.model' => 'base',
    ]);

    $user = User::factory()->create();
    $path = 'voice-turns/test.webm';
    Storage::disk('local')->put($path, str_repeat('a', 512));

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
        'locale' => 'pt-BR',
    ]);

    Http::fake([
        config('rafael.transcriber.url').'*' => Http::response(['text' => 'transcrição pronta']),
    ]);

    (new TranscribeVoiceTurn($voiceTurn))->handle(app(Transcriber::class));

    Http::assertSent(function ($request) {
        $fields = collect($request->data())->mapWithKeys(
            fn (array $field) => [$field['name'] => $field['contents']],
        );

        return str_starts_with($request->url(), config('rafael.transcriber.url'))
            && str_contains($request->url(), 'output=json')
            && str_contains($request->url(), 'language=pt')
            && $request->hasHeader('Authorization', 'Bearer test-transcriber-key')
            && $fields->has('audio_file');
    });
});

it('stores the quota message when transcription credits are exhausted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'voice-turns/test.webm';
    Storage::disk('local')->put($path, 'audio-bytes');

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
    ]);

    mock(Transcriber::class)
        ->shouldReceive('transcribe')
        ->once()
        ->andThrow(new PermanentTranscriptionException('Créditos da API de transcrição esgotados.'));

    (new TranscribeVoiceTurn($voiceTurn))->handle(app(Transcriber::class));

    $voiceTurn->refresh();

    expect($voiceTurn->status)->toBe(VoiceTurnStatus::Failed);
    expect($voiceTurn->error_message)->toBe('Créditos da API de transcrição esgotados.');
});

it('deletes the file and stores a short error when transcription fails', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'voice-turns/test.webm';
    Storage::disk('local')->put($path, 'audio-bytes');

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
    ]);

    mock(Transcriber::class)
        ->shouldReceive('transcribe')
        ->once()
        ->andThrow(new RuntimeException('provider down'));

    (new TranscribeVoiceTurn($voiceTurn))->handle(app(Transcriber::class));

    $voiceTurn->refresh();

    expect($voiceTurn->status)->toBe(VoiceTurnStatus::Failed);
    expect($voiceTurn->error_message)->toBe('Não foi possível transcrever.');
    expect($voiceTurn->audio_path)->toBeNull();
    expect($voiceTurn->disk)->toBeNull();
    expect($voiceTurn->audio_deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('marks the turn failed after the job is exhausted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'voice-turns/test.webm';
    Storage::disk('local')->put($path, 'audio-bytes');

    $voiceTurn = VoiceTurn::factory()->for($user)->uploaded()->create([
        'disk' => 'local',
        'audio_path' => $path,
    ]);

    $job = new TranscribeVoiceTurn($voiceTurn);
    $job->failed(new RuntimeException('still failing'));

    $voiceTurn->refresh();

    expect($voiceTurn->status)->toBe(VoiceTurnStatus::Failed);
    expect($voiceTurn->error_message)->toBe('Não foi possível transcrever.');
    Storage::disk('local')->assertMissing($path);
});

it('returns when the turn is already gone', function () {
    Storage::fake('local');

    $voiceTurn = VoiceTurn::factory()->uploaded()->create([
        'disk' => 'local',
        'audio_path' => 'voice-turns/missing.webm',
    ]);

    Storage::disk('local')->put('voice-turns/missing.webm', 'audio-bytes');

    $job = new TranscribeVoiceTurn($voiceTurn);
    $voiceTurn->delete();

    mock(Transcriber::class)->shouldNotReceive('transcribe');

    $job->handle(app(Transcriber::class));

    expect(VoiceTurn::query()->count())->toBe(0);
});
