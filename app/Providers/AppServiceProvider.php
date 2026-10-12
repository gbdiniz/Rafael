<?php

namespace App\Providers;

use App\Contracts\SpeechPhraser;
use App\Contracts\SpeechSynthesizer;
use App\Contracts\Transcriber;
use App\Contracts\TurnInterpreter;
use App\Services\Interpretation\HttpSpeechPhraser;
use App\Services\Interpretation\HttpTurnInterpreter;
use App\Services\Speech\HttpSpeechSynthesizer;
use App\Services\Transcription\HttpTranscriber;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Transcriber::class, HttpTranscriber::class);
        $this->app->bind(TurnInterpreter::class, HttpTurnInterpreter::class);
        $this->app->bind(SpeechSynthesizer::class, HttpSpeechSynthesizer::class);
        $this->app->bind(SpeechPhraser::class, HttpSpeechPhraser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
