<?php

use App\Enums\VoiceTurnStatus;
use App\Jobs\TranscribeVoiceTurn;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\post;

it('creates an uploaded turn and stores the file for a signed-in user', function () {
    Storage::fake('local');
    Queue::fake([TranscribeVoiceTurn::class]);

    $user = User::factory()->create();
    actingAs($user);
    $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

    post(route('voice-turns.store'), [
        'audio' => $audio,
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonStructure(['uuid']);

    $voiceTurn = VoiceTurn::query()->first();

    assertModelExists($voiceTurn);
    expect($voiceTurn->user_id)->toBe($user->id);
    expect($voiceTurn->status)->toBe(VoiceTurnStatus::Uploaded);
    Storage::disk('local')->assertExists($voiceTurn->audio_path);
});

it('rejects an upload that has no file', function () {
    Storage::fake('local');
    Queue::fake([TranscribeVoiceTurn::class]);

    actingAs(User::factory()->create());

    post(route('voice-turns.store'), [], [
        'Accept' => 'application/json',
    ])->assertUnprocessable();

    expect(VoiceTurn::query()->count())->toBe(0);
});

it('does not write the uploaded audio to the public disk', function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake([TranscribeVoiceTurn::class]);

    actingAs(User::factory()->create());
    $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

    post(route('voice-turns.store'), [
        'audio' => $audio,
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('dispatches transcription for that turn', function () {
    Storage::fake('local');
    Queue::fake([TranscribeVoiceTurn::class]);

    actingAs(User::factory()->create());
    $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

    post(route('voice-turns.store'), [
        'audio' => $audio,
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    Queue::assertPushed(TranscribeVoiceTurn::class, function (TranscribeVoiceTurn $job) {
        return $job->voiceTurn->status === VoiceTurnStatus::Uploaded;
    });
});

it('does not create a record when audio is uploaded', function () {
    Storage::fake('local');
    Queue::fake([TranscribeVoiceTurn::class]);

    actingAs(User::factory()->create());
    $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

    post(route('voice-turns.store'), [
        'audio' => $audio,
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    expect(Record::query()->count())->toBe(0);
});
