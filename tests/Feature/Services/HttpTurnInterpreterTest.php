<?php

use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;
use App\Models\User;
use App\Models\VoiceTurn;
use App\Services\Interpretation\HttpTurnInterpreter;
use Illuminate\Support\Facades\Http;

it('posts json_schema to the configured url and includes openrouter extras on that host', function () {
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
                            'intent' => 'question',
                            'topic' => 'tasks',
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'quais são minhas tarefas',
    ]);

    $interpretation = (new HttpTurnInterpreter)->interpret($turn);

    expect($interpretation->intent)->toBe(TurnIntent::Question);
    expect($interpretation->topic)->toBe(QuestionTopic::Tasks);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-interpreter-key')
            && $request->hasHeader('HTTP-Referer', 'https://rafael.test')
            && $request->hasHeader('X-OpenRouter-Title', 'Rafael')
            && data_get($body, 'model') === 'openai/gpt-4o-mini'
            && data_get($body, 'response_format.type') === 'json_schema'
            && data_get($body, 'provider.require_parameters') === true
            && str_contains((string) data_get($body, 'messages.1.content'), 'quais são minhas tarefas')
            && data_get($body, 'response_format.json_schema.schema.properties.action') !== null;
    });
});

it('omits openrouter extras when the configured host is not openrouter', function () {
    Http::preventStrayRequests();

    config([
        'app.url' => 'https://rafael.test',
        'app.name' => 'Rafael',
        'rafael.interpreter.url' => 'https://llm.example.test/v1/chat/completions',
        'rafael.interpreter.api_key' => 'test-interpreter-key',
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
        'rafael.interpreter.connect_timeout' => 5,
        'rafael.interpreter.timeout' => 20,
    ]);

    Http::fake([
        'https://llm.example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'not_about_the_books',
                            'topic' => null,
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'qual a capital da França',
    ]);

    $interpretation = (new HttpTurnInterpreter)->interpret($turn);

    expect($interpretation->intent)->toBe(TurnIntent::NotAboutTheBooks);
    expect($interpretation->topic)->toBeNull();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://llm.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-interpreter-key')
            && ! $request->hasHeader('HTTP-Referer')
            && ! $request->hasHeader('X-OpenRouter-Title')
            && data_get($body, 'response_format.type') === 'json_schema'
            && data_get($body, 'provider') === null;
    });
});
