<?php

namespace App\Services\Interpretation;

use App\Contracts\TurnInterpreter;
use App\Enums\MutationAction;
use App\Enums\QuestionTopic;
use App\Enums\RecordKind;
use App\Enums\TurnIntent;
use App\Interpretation;
use App\Models\VoiceTurn;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ValueError;

class HttpTurnInterpreter implements TurnInterpreter
{
    /**
     * @param  array{timezone?: string, records?: list<array<string, mixed>>, draft?: ?array<string, mixed>, matches?: list<array<string, mixed>>}  $context
     */
    public function interpret(VoiceTurn $turn, array $context = []): Interpretation
    {
        $transcript = $turn->transcript;

        if (! is_string($transcript) || $transcript === '') {
            throw new RuntimeException('Nenhuma transcrição para interpretar.');
        }

        $timezone = $context['timezone'] ?? 'America/Sao_Paulo';

        $response = $this->client()->post(
            config('rafael.interpreter.url'),
            $this->payload($transcript, $context),
        );

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

        return new Interpretation(
            $intent,
            $this->topic($decoded['topic'] ?? null),
            $this->action($decoded['action'] ?? null),
            $this->kind($decoded['kind'] ?? null),
            $this->nullableString($decoded['title'] ?? null),
            $this->scheduledAtUtc($decoded['scheduled_at'] ?? null, $timezone),
            $this->recordId($decoded['record_id'] ?? null),
        );
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
     * @param  array{timezone?: string, records?: list<array<string, mixed>>, draft?: ?array<string, mixed>, matches?: list<array<string, mixed>>}  $context
     * @return array<string, mixed>
     */
    private function payload(string $transcript, array $context): array
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
                    'content' => json_encode([
                        'transcript' => $transcript,
                        'timezone' => $context['timezone'] ?? 'America/Sao_Paulo',
                        'records' => $context['records'] ?? [],
                        'pending_proposal' => $context['draft'] ?? null,
                        'pending_matches' => $context['matches'] ?? [],
                    ], JSON_UNESCAPED_UNICODE),
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
                            'action' => [
                                'type' => ['string', 'null'],
                                'enum' => [
                                    MutationAction::Create->value,
                                    MutationAction::Change->value,
                                    MutationAction::Remove->value,
                                    null,
                                ],
                            ],
                            'kind' => [
                                'type' => ['string', 'null'],
                                'enum' => [
                                    RecordKind::Appointment->value,
                                    RecordKind::Task->value,
                                    null,
                                ],
                            ],
                            'title' => [
                                'type' => ['string', 'null'],
                            ],
                            'scheduled_at' => [
                                'type' => ['string', 'null'],
                            ],
                            'record_id' => [
                                'type' => ['integer', 'null'],
                            ],
                        ],
                        'required' => ['intent', 'topic', 'action', 'kind', 'title', 'scheduled_at', 'record_id'],
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

        return $payload;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Classifique o que Gabriel disse sobre a agenda dele. Responda só no JSON do schema.

intent:
- question: pergunta sobre as tarefas ou compromissos já gravados
- mutation_draft: criar, mudar ou remover um registro, ou completar um rascunho pendente (horário, escolha)
- yes: confirmação (sim)
- no: recusa (não)
- not_about_the_books: qualquer outra coisa, inclusive perguntas gerais ou da internet

topic: só preencha em question. Use tasks, appointments ou both. Caso contrário, null.

Em mutation_draft:
- action: create, change ou remove
- kind: appointment ou task
- title: o nome do registro
- scheduled_at: data e hora ISO 8601 no fuso informado, ou null
- record_id: id de records quando for change/remove ou quando ele escolher um dos pending_matches

Se pending_matches existir e ele nomear um, preencha record_id. Se pending_proposal estiver incompleto e ele só der o horário, complete scheduled_at.

Não responda a pergunta. Não busque na web. Não invente registros que não estão em records.
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

    private function action(mixed $value): ?MutationAction
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return MutationAction::tryFrom($value);
    }

    private function kind(mixed $value): ?RecordKind
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return RecordKind::tryFrom($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function scheduledAtUtc(mixed $value, string $timezone): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value, $timezone)->utc()->toIso8601String();
    }

    private function recordId(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        return (int) $value;
    }

    private function usesOpenRouter(): bool
    {
        return parse_url((string) config('rafael.interpreter.url'), PHP_URL_HOST) === 'openrouter.ai';
    }
}
