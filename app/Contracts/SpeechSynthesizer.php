<?php

namespace App\Contracts;

interface SpeechSynthesizer
{
    public function speak(string $text): bool;
}
