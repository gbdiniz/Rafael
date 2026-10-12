<?php

namespace Tests\Fakes;

use App\Contracts\SpeechPhraser;

class EchoSpeechPhraser implements SpeechPhraser
{
    public function phrase(string $line): string
    {
        return $line;
    }
}
