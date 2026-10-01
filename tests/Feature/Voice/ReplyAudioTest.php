<?php

use App\Contracts\Transcriber;
use App\Jobs\TranscribeVoiceTurn;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('does not store a reply file when transcription completes', function () {
    Storage::fake('local');
    Storage::fake('public');

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
        ->andReturn('qual é a agenda de hoje?');

    (new TranscribeVoiceTurn($voiceTurn))->handle(app(Transcriber::class));

    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
