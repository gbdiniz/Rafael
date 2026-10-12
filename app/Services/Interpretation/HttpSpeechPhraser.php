<?php

namespace App\Services\Interpretation;

use App\Contracts\SpeechPhraser;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HttpSpeechPhraser implements SpeechPhraser
{
    public function phrase(string $line): string
    {
        if ($line === '') {
            throw new RuntimeException('Não há fala para reescrever.');
        }

        $startedAt = hrtime(true);

        try {
            $response = $this->client()->post(
                config('rafael.interpreter.url'),
                $this->payload($line),
            );
        } catch (Throwable $exception) {
            $this->logPhrase($startedAt, succeeded: false, message: $exception->getMessage());

            throw $exception;
        }

        if ($response->failed()) {
            $this->logPhrase(
                $startedAt,
                succeeded: false,
                status: $response->status(),
                message: $response->body(),
            );

            throw new RuntimeException('A fala falhou.');
        }

        try {
            $speech = $this->speech($response->json('choices.0.message.content'));
        } catch (RuntimeException $exception) {
            $this->logPhrase(
                $startedAt,
                succeeded: false,
                status: $response->status(),
                message: $exception->getMessage(),
            );

            throw $exception;
        }

        $this->logPhrase(
            $startedAt,
            succeeded: true,
            status: $response->status(),
            promptTokens: $this->tokenCount($response->json('usage.prompt_tokens')),
            completionTokens: $this->tokenCount($response->json('usage.completion_tokens')),
        );

        return $speech;
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
    private function payload(string $line): array
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
                    'content' => json_encode(['line' => $line], JSON_UNESCAPED_UNICODE),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'speech',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'speech' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['speech'],
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
Reescreva a fala do Rafael para ele dizer em voz alta, em pt-BR.

A linha que você recebe é a única fonte. Pode mudar o jeito de falar. Mantenha cada fato: título, horário e o pedido de confirmação. Não acrescente fato, não faça pergunta nova e não saia dos registros dele.

Responda só no JSON do schema.
PROMPT;
    }

    private function speech(mixed $content): string
    {
        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
        } elseif (is_array($content)) {
            $decoded = $content;
        } else {
            throw new RuntimeException('A fala retornou um texto vazio.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('A fala retornou um JSON inválido.');
        }

        $speech = $decoded['speech'] ?? null;

        if (! is_string($speech) || $speech === '') {
            throw new RuntimeException('A fala retornou um texto vazio.');
        }

        return $speech;
    }

    private function usesOpenRouter(): bool
    {
        return parse_url((string) config('rafael.interpreter.url'), PHP_URL_HOST) === 'openrouter.ai';
    }

    private function logPhrase(
        int $startedAt,
        bool $succeeded,
        ?int $status = null,
        ?string $message = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
    ): void {
        $context = [
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'model' => config('rafael.interpreter.model'),
            'host' => parse_url((string) config('rafael.interpreter.url'), PHP_URL_HOST),
        ];

        if ($status !== null) {
            $context['status'] = $status;
        }

        if ($promptTokens !== null) {
            $context['prompt_tokens'] = $promptTokens;
        }

        if ($completionTokens !== null) {
            $context['completion_tokens'] = $completionTokens;
        }

        if ($message !== null) {
            $context['message'] = $message;
        }

        if ($succeeded) {
            Log::info('Speech phrasing completed.', $context);

            return;
        }

        Log::warning('Speech phrasing failed.', $context);
    }

    private function tokenCount(mixed $value): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return $value;
    }
}
