<?php

namespace App\Providers;

use App\Contracts\Transcriber;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
