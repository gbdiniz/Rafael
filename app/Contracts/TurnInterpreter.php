<?php

namespace App\Contracts;

use App\Interpretation;
use App\Models\VoiceTurn;

interface TurnInterpreter
{
    /**
     * @param  array{timezone?: string, records?: list<array<string, mixed>>, draft?: ?array<string, mixed>, matches?: list<array<string, mixed>>}  $context
     */
    public function interpret(VoiceTurn $turn, array $context = []): Interpretation;
}
