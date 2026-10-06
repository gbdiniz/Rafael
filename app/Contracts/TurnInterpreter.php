<?php

namespace App\Contracts;

use App\Interpretation;
use App\Models\VoiceTurn;

interface TurnInterpreter
{
    public function interpret(VoiceTurn $turn): Interpretation;
}
