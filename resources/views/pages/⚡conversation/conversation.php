<?php

use App\Actions\Conversation\AnswerQuestion;
use App\Actions\Conversation\Briefing;
use App\Actions\Conversation\OpenConversation;
use App\Contracts\TurnInterpreter;
use App\Enums\TurnIntent;
use App\Enums\VoiceTurnStatus;
use App\Models\VoiceTurn;
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

    public function trackVoiceTurn(string $uuid, TurnInterpreter $interpreter, AnswerQuestion $answerQuestion): void
    {
        $this->trackingTurnUuid = $uuid;
        $this->turnError = null;
        $this->lastTranscript = null;
        $this->refreshTurnStatus($interpreter, $answerQuestion);
    }

    public function refreshTurnStatus(TurnInterpreter $interpreter, AnswerQuestion $answerQuestion): void
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

        if ($turn->status !== VoiceTurnStatus::Completed) {
            return;
        }

        if ($turn->error_message !== null) {
            $this->turnError = $turn->error_message;
            $this->trackingTurnUuid = null;

            return;
        }

        if ($turn->consumed_at !== null) {
            $this->trackingTurnUuid = null;

            return;
        }

        try {
            $interpretation = $interpreter->interpret($turn);
        } catch (\Throwable) {
            $turn->update([
                'error_message' => 'Não foi possível entender o que você disse.',
            ]);
            $this->turnError = $turn->error_message;
            $this->trackingTurnUuid = null;

            return;
        }

        $this->lastTranscript = null;

        if ($interpretation->intent === TurnIntent::Question) {
            if ($interpretation->topic === null) {
                $this->message = 'Não entendi se você quer as tarefas ou os compromissos.';
            } else {
                $this->message = $answerQuestion->handle(auth()->user(), $interpretation->topic);
            }

            $this->consume($turn);

            return;
        }

        if ($interpretation->intent === TurnIntent::NotAboutTheBooks) {
            $this->message = 'Eu só falo dos seus compromissos e tarefas.';
            $this->consume($turn);

            return;
        }

        $this->consume($turn);
    }

    private function consume(VoiceTurn $turn): void
    {
        $turn->update([
            'consumed_at' => now(),
        ]);
        $this->trackingTurnUuid = null;
    }
};
