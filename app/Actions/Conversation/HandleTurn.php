<?php

namespace App\Actions\Conversation;

use App\Actions\Records\ChangeRecord;
use App\Actions\Records\CreateRecord;
use App\Actions\Records\RemoveRecord;
use App\Enums\MutationAction;
use App\Enums\RecordKind;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HandleTurn
{
    public function __construct(
        private AnswerQuestion $answerQuestion,
        private Briefing $briefing,
        private CreateRecord $createRecord,
        private ChangeRecord $changeRecord,
        private RemoveRecord $removeRecord,
    ) {}

    /**
     * @param  array<string, mixed>|null  $proposal
     * @param  array<int, array<string, mixed>>  $matches
     * @param  array<string, mixed>|null  $pendingPatch
     */
    public function handle(
        User $user,
        VoiceTurn $turn,
        Interpretation $interpretation,
        ?array $proposal,
        array $matches,
        ?array $pendingPatch,
        string $currentMessage,
    ): TurnOutcome {
        if ($matches !== [] && $interpretation->intent !== TurnIntent::No) {
            $chosen = $this->chosenMatch($interpretation, $matches);

            if ($chosen !== null) {
                return $this->proposalFromMatch($user, $turn, $chosen, $pendingPatch);
            }

            if ($interpretation->intent !== TurnIntent::Yes) {
                return new TurnOutcome(
                    $this->nameMatches($matches),
                    matches: $matches,
                    pendingPatch: $pendingPatch,
                );
            }
        }

        return match ($interpretation->intent) {
            TurnIntent::Question => $this->question($user, $interpretation),
            TurnIntent::NotAboutTheBooks => new TurnOutcome('Eu só falo dos seus compromissos e tarefas.'),
            TurnIntent::Yes => $this->confirm($user, $proposal, $currentMessage),
            TurnIntent::No => $this->reject($user),
            TurnIntent::MutationDraft => $this->mutate($user, $turn, $interpretation, $proposal),
        };
    }

    private function question(User $user, Interpretation $interpretation): TurnOutcome
    {
        if ($interpretation->topic === null) {
            return new TurnOutcome('Não entendi se você quer as tarefas ou os compromissos.');
        }

        return new TurnOutcome($this->answerQuestion->handle($user, $interpretation->topic));
    }

    /**
     * @param  array<string, mixed>|null  $proposal
     */
    private function confirm(User $user, ?array $proposal, string $currentMessage): TurnOutcome
    {
        if ($proposal === null || ($proposal['incomplete'] ?? false) === true) {
            return new TurnOutcome($currentMessage, $proposal);
        }

        $action = MutationAction::from($proposal['action']);
        $kind = RecordKind::from($proposal['kind']);
        $scheduledAt = $this->carbon($proposal['scheduled_at'] ?? null);
        $transcript = $proposal['describing_transcript'];
        $turnId = $this->describingTurnId($proposal['describing_turn_id']);

        if ($action === MutationAction::Create) {
            $this->createRecord->handle(
                $user,
                $kind,
                $proposal['title'],
                $scheduledAt,
                $transcript,
                $turnId,
            );
        }

        if ($action === MutationAction::Change) {
            $record = $this->ownedRecord($user, $proposal['record_id']);

            if ($record === null) {
                return new TurnOutcome($currentMessage);
            }

            $this->changeRecord->handle(
                $record,
                $kind,
                $proposal['title'],
                $scheduledAt,
                $transcript,
                $turnId,
            );
        }

        if ($action === MutationAction::Remove) {
            $record = $this->ownedRecord($user, $proposal['record_id']);

            if ($record === null) {
                return new TurnOutcome($currentMessage);
            }

            $this->removeRecord->handle($record);
        }

        return new TurnOutcome('Pronto.');
    }

    private function reject(User $user): TurnOutcome
    {
        return new TurnOutcome($this->briefing->handle($user, now()));
    }

    /**
     * @param  array<string, mixed>|null  $proposal
     */
    private function mutate(User $user, VoiceTurn $turn, Interpretation $interpretation, ?array $proposal): TurnOutcome
    {
        $interpretation = $this->mergeIncompleteDraft($interpretation, $proposal);
        $action = $interpretation->action ?? MutationAction::Create;

        if ($action === MutationAction::Create) {
            return $this->proposeCreate($user, $turn, $interpretation);
        }

        return $this->proposeChangeOrRemove($user, $turn, $interpretation, $action);
    }

    /**
     * @param  array<string, mixed>|null  $proposal
     */
    private function mergeIncompleteDraft(Interpretation $interpretation, ?array $proposal): Interpretation
    {
        if ($proposal === null || ($proposal['incomplete'] ?? false) !== true) {
            return $interpretation;
        }

        return new Interpretation(
            $interpretation->intent,
            $interpretation->topic,
            $interpretation->action ?? MutationAction::tryFrom($proposal['action'] ?? ''),
            $interpretation->kind ?? RecordKind::tryFrom($proposal['kind'] ?? ''),
            $interpretation->title ?? $proposal['title'] ?? null,
            $interpretation->scheduledAt ?? $proposal['scheduled_at'] ?? null,
            $interpretation->recordId ?? $proposal['record_id'] ?? null,
        );
    }

    private function proposeCreate(User $user, VoiceTurn $turn, Interpretation $interpretation): TurnOutcome
    {
        $kind = $interpretation->kind ?? RecordKind::Task;
        $title = $interpretation->title;

        if ($title === null || $title === '') {
            return new TurnOutcome('Não entendi o título.');
        }

        $proposal = $this->proposalArray(
            MutationAction::Create,
            $kind,
            $title,
            $interpretation->scheduledAt,
            null,
            $turn,
        );

        if ($kind === RecordKind::Appointment && $interpretation->scheduledAt === null) {
            $proposal['incomplete'] = true;

            return new TurnOutcome('Para ser compromisso, preciso do horário.', $proposal);
        }

        return new TurnOutcome($this->repeat($proposal, $user->timezone), $proposal);
    }

    private function proposeChangeOrRemove(
        User $user,
        VoiceTurn $turn,
        Interpretation $interpretation,
        MutationAction $action,
    ): TurnOutcome {
        $hits = $this->matchingRecords($user, $interpretation);

        if ($hits->isEmpty()) {
            return new TurnOutcome('Não encontrei esse registro.');
        }

        if ($hits->count() > 1) {
            $matches = $hits->map(fn (Record $record) => $this->matchArray($record, $user->timezone))->all();

            return new TurnOutcome(
                $this->nameMatches($matches),
                matches: $matches,
                pendingPatch: [
                    'action' => $action->value,
                    'kind' => $interpretation->kind?->value,
                    'title' => null,
                    'scheduled_at' => $interpretation->scheduledAt,
                    'describing_turn_id' => $turn->id,
                    'describing_transcript' => $turn->transcript,
                ],
            );
        }

        return $this->proposalFromRecord($user, $turn, $hits->first(), $action, $interpretation);
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>|null  $pendingPatch
     */
    private function proposalFromMatch(User $user, VoiceTurn $turn, array $match, ?array $pendingPatch): TurnOutcome
    {
        $action = MutationAction::from($pendingPatch['action'] ?? MutationAction::Change->value);
        $record = $this->ownedRecord($user, $match['id']);

        if ($record === null) {
            return new TurnOutcome('Não encontrei esse registro.');
        }

        $kind = RecordKind::tryFrom($pendingPatch['kind'] ?? '') ?? $record->kind;
        $title = $pendingPatch['title'] ?? $record->title;
        $scheduledAt = $pendingPatch['scheduled_at'] ?? $record->scheduled_at?->toIso8601String();

        $describingTurn = VoiceTurn::query()->find($pendingPatch['describing_turn_id'] ?? $turn->id) ?? $turn;

        $interpretation = new Interpretation(
            TurnIntent::MutationDraft,
            action: $action,
            kind: $kind,
            title: $title,
            scheduledAt: $scheduledAt,
            recordId: $record->id,
        );

        return $this->proposalFromRecord($user, $describingTurn, $record, $action, $interpretation);
    }

    private function proposalFromRecord(
        User $user,
        VoiceTurn $turn,
        Record $record,
        MutationAction $action,
        Interpretation $interpretation,
    ): TurnOutcome {
        $kind = $interpretation->kind ?? $record->kind;
        $title = $interpretation->title ?? $record->title;
        $scheduledAt = $interpretation->scheduledAt ?? $record->scheduled_at?->utc()->toIso8601String();

        if ($action === MutationAction::Change
            && $kind === RecordKind::Appointment
            && $record->scheduled_at === null
            && $interpretation->scheduledAt === null) {
            $proposal = $this->proposalArray($action, $kind, $title, null, $record->id, $turn);
            $proposal['incomplete'] = true;

            return new TurnOutcome('Para ser compromisso, preciso do horário.', $proposal);
        }

        if ($action === MutationAction::Change && $kind === RecordKind::Appointment && $scheduledAt === null) {
            $proposal = $this->proposalArray($action, $kind, $title, null, $record->id, $turn);
            $proposal['incomplete'] = true;

            return new TurnOutcome('Para ser compromisso, preciso do horário.', $proposal);
        }

        if ($action === MutationAction::Remove) {
            $title = $record->title;
            $kind = $record->kind;
            $scheduledAt = $record->scheduled_at?->utc()->toIso8601String();
        }

        $proposal = $this->proposalArray($action, $kind, $title, $scheduledAt, $record->id, $turn);

        return new TurnOutcome($this->repeat($proposal, $user->timezone), $proposal);
    }

    /**
     * @return Collection<int, Record>
     */
    private function matchingRecords(User $user, Interpretation $interpretation): Collection
    {
        $query = Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->orderBy('scheduled_at')
            ->orderBy('id');

        if ($interpretation->recordId !== null) {
            $record = (clone $query)->whereKey($interpretation->recordId)->first();

            return $record === null ? collect() : collect([$record]);
        }

        if ($interpretation->title === null || $interpretation->title === '') {
            return collect();
        }

        $needle = mb_strtolower($interpretation->title);

        return $query
            ->get()
            ->filter(fn (Record $record) => str_contains(mb_strtolower($record->title), $needle))
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, mixed>|null
     */
    private function chosenMatch(Interpretation $interpretation, array $matches): ?array
    {
        if ($interpretation->recordId !== null) {
            foreach ($matches as $match) {
                if ((int) $match['id'] === $interpretation->recordId) {
                    return $match;
                }
            }
        }

        if ($interpretation->title === null || $interpretation->title === '') {
            return null;
        }

        $needle = mb_strtolower($interpretation->title);
        $hits = array_values(array_filter(
            $matches,
            fn (array $match) => str_contains(mb_strtolower($match['title']), $needle)
                || str_contains($needle, mb_strtolower($match['title'])),
        ));

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     */
    private function nameMatches(array $matches): string
    {
        $titles = collect($matches)->pluck('title')->join(', ');

        return 'Encontrei estes: '.$titles.'. Qual você quer?';
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalArray(
        MutationAction $action,
        RecordKind $kind,
        string $title,
        ?string $scheduledAt,
        ?int $recordId,
        VoiceTurn $turn,
    ): array {
        return [
            'action' => $action->value,
            'kind' => $kind->value,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'record_id' => $recordId,
            'describing_turn_id' => $turn->id,
            'describing_transcript' => $turn->transcript,
            'incomplete' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchArray(Record $record, string $timezone): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'kind' => $record->kind->value,
            'scheduled_at' => $record->scheduled_at?->timezone($timezone)->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function repeat(array $proposal, string $timezone): string
    {
        $type = $proposal['kind'] === RecordKind::Appointment->value ? 'compromisso' : 'tarefa';
        $title = $proposal['title'];
        $when = $this->formatWhen($proposal['scheduled_at'] ?? null, $timezone);

        return match ($proposal['action']) {
            MutationAction::Create->value => $when === null
                ? "Vou anotar a {$type} {$title}. Confirma?"
                : ($proposal['kind'] === RecordKind::Task->value
                    ? "Vou anotar a tarefa {$title} com prazo {$when}. Confirma?"
                    : "Vou anotar o compromisso {$title} em {$when}. Confirma?"),
            MutationAction::Change->value => $when === null
                ? "Vou mudar para {$type} {$title}. Confirma?"
                : "Vou mudar para {$type} {$title} em {$when}. Confirma?",
            MutationAction::Remove->value => "Vou remover {$title}. Confirma?",
            default => "Confirma {$title}?",
        };
    }

    private function formatWhen(?string $utc, string $timezone): ?string
    {
        if ($utc === null) {
            return null;
        }

        return Carbon::parse($utc)->timezone($timezone)->format('d/m/Y H:i');
    }

    private function carbon(?string $utc): ?Carbon
    {
        if ($utc === null) {
            return null;
        }

        return Carbon::parse($utc)->utc();
    }

    private function ownedRecord(User $user, mixed $id): ?Record
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        return Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->whereKey((int) $id)
            ->first();
    }

    private function describingTurnId(mixed $id): ?int
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $turnId = (int) $id;

        return VoiceTurn::query()->whereKey($turnId)->exists() ? $turnId : null;
    }
}
