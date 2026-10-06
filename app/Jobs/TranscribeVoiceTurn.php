<?php

namespace App\Jobs;

use App\Contracts\Transcriber;
use App\Enums\VoiceTurnStatus;
use App\Exceptions\PermanentTranscriptionException;
use App\Exceptions\TransientTranscriptionException;
use App\Models\VoiceTurn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TranscribeVoiceTurn implements ShouldQueue
{
    use Queueable;

    public int $timeout = 85;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(public VoiceTurn $voiceTurn) {}

    public function handle(Transcriber $transcriber): void
    {
        $turn = VoiceTurn::query()->find($this->voiceTurn->id);

        if ($turn === null) {
            return;
        }

        if ($turn->disk === null || $turn->audio_path === null) {
            return;
        }

        $turn->update([
            'status' => VoiceTurnStatus::Transcribing,
        ]);

        try {
            $transcript = $transcriber->transcribe($turn);
        } catch (TransientTranscriptionException $exception) {
            throw $exception;
        } catch (PermanentTranscriptionException $exception) {
            $this->markFailed($turn, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->markFailed($turn, 'Não foi possível transcrever.');

            return;
        }

        Storage::disk($turn->disk)->delete($turn->audio_path);

        $turn->update([
            'status' => VoiceTurnStatus::Completed,
            'transcript' => $transcript,
            'audio_path' => null,
            'disk' => null,
            'audio_deleted_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $turn = VoiceTurn::query()->find($this->voiceTurn->id);

        if ($turn === null) {
            return;
        }

        $this->markFailed($turn, 'Não foi possível transcrever.');
    }

    private function markFailed(VoiceTurn $turn, string $message): void
    {
        if ($turn->disk !== null && $turn->audio_path !== null) {
            Storage::disk($turn->disk)->delete($turn->audio_path);
        }

        $turn->update([
            'status' => VoiceTurnStatus::Failed,
            'error_message' => Str::limit($message, 255, ''),
            'audio_path' => null,
            'disk' => null,
            'audio_deleted_at' => now(),
        ]);
    }
}
