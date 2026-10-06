<?php

namespace App\Actions\Voice;

use App\Enums\VoiceTurnStatus;
use App\Jobs\TranscribeVoiceTurn;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreVoiceTurn
{
    public function handle(User $user, UploadedFile $audio): VoiceTurn
    {
        $uuid = (string) Str::uuid();
        $path = $audio->storeAs('voice-turns', "{$uuid}.webm", 'local');

        $voiceTurn = $user->voiceTurns()->create([
            'uuid' => $uuid,
            'status' => VoiceTurnStatus::Uploaded,
            'locale' => 'pt-BR',
            'disk' => 'local',
            'audio_path' => $path,
        ]);

        TranscribeVoiceTurn::dispatch($voiceTurn);

        return $voiceTurn;
    }
}
