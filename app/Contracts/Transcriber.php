<?php

namespace App\Contracts;

use App\Models\VoiceTurn;

interface Transcriber
{
    public function transcribe(VoiceTurn $turn): string;
}
