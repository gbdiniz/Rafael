<?php

namespace App\Actions\Records;

use App\Enums\RecordKind;
use App\Models\Record;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ChangeRecord
{
    public function handle(
        Record $record,
        RecordKind $kind,
        string $title,
        ?Carbon $scheduledAt,
        string $lastTranscript,
        ?int $lastVoiceTurnId,
    ): Record {
        if ($kind === RecordKind::Appointment && $scheduledAt === null) {
            throw new InvalidArgumentException('Um compromisso precisa de horário.');
        }

        $record->update([
            'kind' => $kind,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'last_transcript' => $lastTranscript,
            'last_voice_turn_id' => $lastVoiceTurnId,
        ]);

        return $record->refresh();
    }
}
