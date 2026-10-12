<?php

use App\Services\Interpretation\HttpSpeechPhraser;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('returns the speech string from the completion', function () {
    Http::preventStrayRequests();

    config([
        'app.url' => 'https://rafael.test',
        'app.name' => 'Rafael',
        'rafael.interpreter.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'rafael.interpreter.api_key' => 'test-interpreter-key',
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
        'rafael.interpreter.connect_timeout' => 5,
        'rafael.interpreter.timeout' => 20,
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'speech' => 'Certo. Dentista, amanhã às quinze. Posso marcar?',
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 12,
                'completion_tokens' => 8,
            ],
        ]),
    ]);

    $logged = [];
    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $speech = (new HttpSpeechPhraser)->phrase('Dentista, amanhã 15:00. Posso marcar?');

    expect($speech)->toBe('Certo. Dentista, amanhã às quinze. Posso marcar?');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-interpreter-key')
            && $request->hasHeader('HTTP-Referer', 'https://rafael.test')
            && $request->hasHeader('X-OpenRouter-Title', 'Rafael')
            && data_get($body, 'model') === 'openai/gpt-4o-mini'
            && data_get($body, 'response_format.json_schema.schema.required') === ['speech']
            && data_get($body, 'provider.require_parameters') === true
            && str_contains((string) data_get($body, 'messages.1.content'), 'Dentista, amanhã 15:00. Posso marcar?');
    });

    expect(collect($logged)->contains(
        fn (MessageLogged $message) => $message->message === 'Speech phrasing completed.',
    ))->toBeTrue();
});

it('throws when the speech completion fails', function () {
    Http::preventStrayRequests();

    config([
        'rafael.interpreter.url' => 'https://llm.example.test/v1/chat/completions',
        'rafael.interpreter.api_key' => null,
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
    ]);

    Http::fake([
        'https://llm.example.test/v1/chat/completions' => Http::response('nope', 500),
    ]);

    expect(fn () => (new HttpSpeechPhraser)->phrase('Pronto.'))
        ->toThrow(RuntimeException::class);
});
