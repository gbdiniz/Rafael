<?php

namespace App\Console\Commands;

use App\Models\VoiceTurn;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('voice-turns:prune')]
#[Description('Hard-delete voice turns older than seven days')]
class PruneVoiceTurnsCommand extends Command
{
    public function handle(): int
    {
        VoiceTurn::query()
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        return self::SUCCESS;
    }
}
