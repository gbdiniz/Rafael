<?php

namespace App\Services\Speech;

use App\Contracts\SpeechSynthesizer;

class HttpSpeechSynthesizer implements SpeechSynthesizer
{
    public function speak(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $url = config('rafael.piper.url');

        return is_string($url) && $url !== '';
    }
}
