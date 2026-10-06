<?php

namespace App\Actions\Records;

use App\Enums\RecordKind;
use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CreateRecord
{
    public function handle(
        User $user,
        RecordKind $kind,
        string $title,
        ?Carbon $scheduledAt,
        string $lastTranscript,
        ?int $lastVoiceTurnId,
    ): Record {
        if ($kind === RecordKind::Appointment && $scheduledAt === null) {
            throw new InvalidArgumentException('Um compromisso precisa de horário.');
        }

        return $user->records()->create([
            'kind' => $kind,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'last_transcript' => $lastTranscript,
            'last_voice_turn_id' => $lastVoiceTurnId,
        ]);
    }
}
