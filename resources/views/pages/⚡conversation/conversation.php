<?php

use App\Actions\Conversation\Briefing;
use App\Actions\Conversation\HandleTurn;
use App\Actions\Conversation\OpenConversation;
use App\Contracts\TurnInterpreter;
use App\Enums\VoiceTurnStatus;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Facades\App;
use Livewire\Component;

new class extends Component
{
    public string $message = '';

    public ?string $trackingTurnUuid = null;

    public ?string $turnError = null;

    public ?string $lastTranscript = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $proposal = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $matches = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $pendingPatch = null;

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

    public function trackVoiceTurn(string $uuid, TurnInterpreter $interpreter, HandleTurn $handleTurn): void
    {
        $this->trackingTurnUuid = $uuid;
        $this->turnError = null;
        $this->lastTranscript = null;
        $this->refreshTurnStatus($interpreter, $handleTurn);
    }

    public function refreshTurnStatus(TurnInterpreter $interpreter, HandleTurn $handleTurn): void
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

        $user = auth()->user();

        try {
            $interpretation = $interpreter->interpret($turn, $this->interpreterContext($user));
        } catch (\Throwable) {
            $turn->update([
                'error_message' => 'Não foi possível entender o que você disse.',
            ]);
            $this->turnError = $turn->error_message;
            $this->trackingTurnUuid = null;

            return;
        }

        $this->lastTranscript = null;

        $outcome = $handleTurn->handle(
            $user,
            $turn,
            $interpretation,
            $this->proposal,
            $this->matches,
            $this->pendingPatch,
            $this->message,
        );

        $this->message = $outcome->message;
        $this->proposal = $outcome->proposal;
        $this->matches = $outcome->matches;
        $this->pendingPatch = $outcome->pendingPatch;

        $this->consume($turn);
    }

    /**
     * @return array{timezone: string, records: list<array<string, mixed>>, draft: ?array<string, mixed>, matches: list<array<string, mixed>>}
     */
    private function interpreterContext(User $user): array
    {
        $records = Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Record $record) => [
                'id' => $record->id,
                'kind' => $record->kind->value,
                'title' => $record->title,
                'scheduled_at' => $record->scheduled_at?->timezone($user->timezone)->toIso8601String(),
            ])
            ->all();

        return [
            'timezone' => $user->timezone,
            'records' => $records,
            'draft' => $this->proposal,
            'matches' => $this->matches,
        ];
    }

    private function consume(VoiceTurn $turn): void
    {
        $turn->update([
            'consumed_at' => now(),
        ]);
        $this->trackingTurnUuid = null;
    }
};
