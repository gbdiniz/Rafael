<?php

namespace App\Services\Interpretation;

use App\Contracts\TurnInterpreter;
use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\VoiceTurn;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ValueError;

class HttpTurnInterpreter implements TurnInterpreter
{
    public function interpret(VoiceTurn $turn): Interpretation
    {
        $transcript = $turn->transcript;

        if (! is_string($transcript) || $transcript === '') {
            throw new RuntimeException('Nenhuma transcrição para interpretar.');
        }

        $response = $this->client()->post(config('rafael.interpreter.url'), $this->payload($transcript));

        if ($response->failed()) {
            Log::warning('Interpretation request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('A interpretação falhou.');
        }

        $decoded = $this->decodedContent($response->json('choices.0.message.content'));

        try {
            $intent = TurnIntent::from($decoded['intent'] ?? '');
        } catch (ValueError $exception) {
            throw new RuntimeException('A interpretação retornou um intent inválido.', previous: $exception);
        }

        return new Interpretation($intent, $this->topic($decoded['topic'] ?? null));
    }

    private function client(): PendingRequest
    {
        $client = Http::connectTimeout((int) config('rafael.interpreter.connect_timeout', 5))
            ->timeout((int) config('rafael.interpreter.timeout', 20))
            ->acceptJson()
            ->asJson();

        $apiKey = config('rafael.interpreter.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        if ($this->usesOpenRouter()) {
            $client = $client->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-OpenRouter-Title' => config('app.name'),
            ]);
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $transcript): array
    {
        $payload = [
            'model' => config('rafael.interpreter.model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $transcript,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'interpretation',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'intent' => [
                                'type' => 'string',
                                'enum' => [
                                    TurnIntent::Question->value,
                                    TurnIntent::MutationDraft->value,
                                    TurnIntent::Yes->value,
                                    TurnIntent::No->value,
                                    TurnIntent::NotAboutTheBooks->value,
                                ],
                            ],
                            'topic' => [
                                'type' => ['string', 'null'],
                                'enum' => [
                                    QuestionTopic::Tasks->value,
                                    QuestionTopic::Appointments->value,
                                    QuestionTopic::Both->value,
                                    null,
                                ],
                            ],
                        ],
                        'required' => ['intent', 'topic'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        if ($this->usesOpenRouter()) {
            $payload['provider'] = [
                'require_parameters' => true,
            ];
        }

        Log::info('Payload for interpreter', ['payload' => $payload]);

        return $payload;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Classifique o que Gabriel disse sobre a agenda dele. Responda só no JSON do schema.

intent:
- question: pergunta sobre as tarefas ou compromissos já gravados
- mutation_draft: criar, mudar ou remover um registro
- yes: confirmação (sim)
- no: recusa (não)
- not_about_the_books: qualquer outra coisa, inclusive perguntas gerais ou da internet

topic: só preencha em question. Use tasks, appointments ou both. Caso contrário, null.

Não responda a pergunta. Não busque na web. Não invente registros.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedContent(mixed $content): array
    {
        if (is_array($content)) {
            return $content;
        }

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('A interpretação retornou um texto vazio.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('A interpretação retornou um JSON inválido.');
        }

        return $decoded;
    }

    private function topic(mixed $value): ?QuestionTopic
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return QuestionTopic::tryFrom($value);
    }

    private function usesOpenRouter(): bool
    {
        return parse_url((string) config('rafael.interpreter.url'), PHP_URL_HOST) === 'openrouter.ai';
    }
}
