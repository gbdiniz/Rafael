<?php

namespace App\Contracts;

interface SpeechPhraser
{
    public function phrase(string $line): string;
}
