<?php

namespace App\Services\Speech;

use App\Contracts\SpeechSynthesizer;

class BrowserSpeechSynthesizer implements SpeechSynthesizer
{
    public function speak(string $text): bool
    {
        return $text !== '';
    }
}
