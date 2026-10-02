<?php

namespace App\Actions\Conversation;

use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Carbon;

class Briefing
{
    public function handle(User $user, Carbon $now): string
    {
        $timezone = $user->timezone;

        $missedAppointments = Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->scheduledBeforeLocalDay($timezone, $now)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $todaysAppointments = Record::query()
            ->where('user_id', $user->id)
            ->onTheBooks()
            ->scheduledOnLocalDay($timezone, $now)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $parts = [];

        if ($missedAppointments->isNotEmpty()) {
            $parts[] = 'Compromissos pendentes: '.$missedAppointments->pluck('title')->join(', ').'.';
        }

        if ($todaysAppointments->isNotEmpty()) {
            $parts[] = 'Compromissos de hoje: '.$todaysAppointments->pluck('title')->join(', ').'.';
        }

        if ($parts === []) {
            return 'Fala ai! Nenhum comprimisso hoje.';
        }

        return implode(' ', $parts);
    }
}
