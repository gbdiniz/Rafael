<?php

namespace App\Actions\Conversation;

use App\Enums\QuestionTopic;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnswerQuestion
{
    public function handle(User $user, QuestionTopic $topic): string
    {
        return match ($topic) {
            QuestionTopic::Tasks => $this->tasks($user),
            QuestionTopic::Appointments => $this->appointments($user),
            QuestionTopic::Both => $this->tasks($user).' '.$this->appointments($user),
        };
    }

    private function tasks(User $user): string
    {
        $tasks = $this->onTheBooks($user)->tasks()->get();

        if ($tasks->isEmpty()) {
            return 'Nenhuma tarefa nos livros.';
        }

        return 'Tarefas: '.$this->joinTitles($tasks, $user, withDueLabel: true).'.';
    }

    private function appointments(User $user): string
    {
        $appointments = $this->onTheBooks($user)->appointments()->get();

        if ($appointments->isEmpty()) {
            return 'Nenhum compromisso nos livros.';
        }

        return 'Compromissos: '.$this->joinTitles($appointments, $user, withDueLabel: false).'.';
    }

    /**
     * @return Builder<Record>
     */
    private function onTheBooks(User $user): Builder
    {
        return Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->orderBy('scheduled_at')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, Record>  $records
     */
    private function joinTitles(Collection $records, User $user, bool $withDueLabel): string
    {
        return $records
            ->map(function (Record $record) use ($user, $withDueLabel): string {
                if ($record->scheduled_at === null) {
                    return $record->title;
                }

                $when = $record->scheduled_at->timezone($user->timezone)->format('d/m/Y H:i');

                if ($withDueLabel) {
                    return $record->title.' (prazo '.$when.')';
                }

                return $record->title.' ('.$when.')';
            })
            ->join(', ');
    }
}
