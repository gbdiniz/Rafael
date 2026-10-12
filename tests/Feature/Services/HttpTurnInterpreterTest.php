<?php

use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;
use App\Models\User;
use App\Models\VoiceTurn;
use App\Services\Interpretation\HttpTurnInterpreter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\travelTo;

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

it('sends the current local date so relative dates resolve from today', function () {
    Http::preventStrayRequests();
    travelTo(Carbon::parse('2026-10-12 02:30:00', 'UTC'));

    config([
        'rafael.interpreter.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'rafael.interpreter.api_key' => 'test-interpreter-key',
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'mutation_draft',
                            'topic' => null,
                            'action' => 'create',
                            'kind' => 'appointment',
                            'title' => 'médico',
                            'scheduled_at' => '2026-10-12T19:00:00-03:00',
                            'record_id' => null,
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'agenda para amanhã às sete da noite',
    ]);

    (new HttpTurnInterpreter)->interpret($turn, [
        'timezone' => 'America/Sao_Paulo',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $userMessage = json_decode((string) data_get($body, 'messages.1.content'), true);
        $systemPrompt = (string) data_get($body, 'messages.0.content');

        return is_array($userMessage)
            && ($userMessage['timezone'] ?? null) === 'America/Sao_Paulo'
            && ($userMessage['now'] ?? null) === '2026-10-11T23:30:00-03:00'
            && ($userMessage['weekday'] ?? null) === 'domingo'
            && str_contains($systemPrompt, 'a partir de now')
            && str_contains($systemPrompt, 'próxima ocorrência futura');
    });
});

it('logs how long an OpenRouter interpretation took', function () {
    Http::preventStrayRequests();

    config([
        'rafael.interpreter.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'rafael.interpreter.api_key' => 'test-interpreter-key',
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
    ]);

    $logged = [];

    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'model' => 'openai/gpt-4o-mini',
            'usage' => [
                'prompt_tokens' => 120,
                'completion_tokens' => 18,
            ],
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'question',
                            'topic' => 'tasks',
                            'action' => null,
                            'kind' => null,
                            'title' => null,
                            'scheduled_at' => null,
                            'record_id' => null,
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

    $completion = collect($logged)->first(
        fn (MessageLogged $message) => $message->message === 'Interpretation completed.',
    );

    expect($completion)->toBeInstanceOf(MessageLogged::class);
    expect($completion->level)->toBe('info');
    expect($completion->context)->toMatchArray([
        'voice_turn_id' => $turn->id,
        'model' => 'openai/gpt-4o-mini',
        'host' => 'openrouter.ai',
        'status' => 200,
        'intent' => 'question',
        'prompt_tokens' => 120,
        'completion_tokens' => 18,
    ]);
    expect($completion->context['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
    expect(json_encode($completion->context))->not->toContain('quais são minhas tarefas');
});

it('logs how long a failed OpenRouter interpretation took', function () {
    Http::preventStrayRequests();

    config([
        'rafael.interpreter.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'rafael.interpreter.api_key' => 'test-interpreter-key',
        'rafael.interpreter.model' => 'openai/gpt-4o-mini',
    ]);

    $logged = [];

    Log::listen(function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response('unavailable', 503),
    ]);

    $user = User::factory()->create();
    $turn = VoiceTurn::factory()->for($user)->completed()->create([
        'transcript' => 'quais são minhas tarefas',
    ]);

    expect(fn () => (new HttpTurnInterpreter)->interpret($turn))
        ->toThrow(RuntimeException::class, 'A interpretação falhou.');

    $failure = collect($logged)->first(
        fn (MessageLogged $message) => $message->message === 'Interpretation request failed.',
    );

    expect($failure)->toBeInstanceOf(MessageLogged::class);
    expect($failure->level)->toBe('warning');
    expect($failure->context)->toMatchArray([
        'voice_turn_id' => $turn->id,
        'model' => 'openai/gpt-4o-mini',
        'host' => 'openrouter.ai',
        'status' => 503,
        'message' => 'unavailable',
    ]);
    expect($failure->context['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
});
