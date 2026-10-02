<?php

use App\Actions\Conversation\Briefing;
use App\Actions\Conversation\OpenConversation;
use App\Models\VoiceTurn;
use App\VoiceTurnStatus;
use Illuminate\Support\Facades\App;
use Livewire\Component;

new class extends Component
{
    public string $message = '';

    public ?string $trackingTurnUuid = null;

    public ?string $turnError = null;

    public ?string $lastTranscript = null;

    public function mount(OpenConversation $openConversation, Briefing $briefing): void
    {
        App::setLocale('pt_BR');

        $user = auth()->user();

        if ($user->conversation_opened_at === null) {
            $this->message = $openConversation->handle($user);

            return;
        }

        $this->message = $briefing->handle($user, now());
    }

    public function trackVoiceTurn(string $uuid): void
    {
        $this->trackingTurnUuid = $uuid;
        $this->turnError = null;
        $this->lastTranscript = null;
        $this->refreshTurnStatus();
    }

    public function refreshTurnStatus(): void
    {
        if ($this->trackingTurnUuid === null) {
            return;
        }

        $turn = VoiceTurn::query()
            ->where('user_id', auth()->id())
            ->where('uuid', $this->trackingTurnUuid)
            ->first();

        if ($turn === null) {
            $this->trackingTurnUuid = null;

            return;
        }

        if ($turn->status === VoiceTurnStatus::Failed) {
            $this->turnError = $turn->error_message;
            $this->trackingTurnUuid = null;

            return;
        }

        if ($turn->status === VoiceTurnStatus::Completed) {
            $this->lastTranscript = $turn->transcript;
            $this->trackingTurnUuid = null;
        }
    }
};
