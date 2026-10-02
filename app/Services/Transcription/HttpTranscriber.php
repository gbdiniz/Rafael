<?php

namespace App\Services\Transcription;

use App\Contracts\Transcriber;
use App\Exceptions\PermanentTranscriptionException;
use App\Exceptions\TransientTranscriptionException;
use App\Models\VoiceTurn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HttpTranscriber implements Transcriber
{
    public function transcribe(VoiceTurn $turn): string
    {
        if ($turn->disk === null || $turn->audio_path === null) {
            throw new RuntimeException('Nenhum áudio para transcrição.');
        }

        $disk = Storage::disk($turn->disk);
        $contents = $disk->get($turn->audio_path);

        if ($contents === '' || strlen($contents) < 100) {
            throw new PermanentTranscriptionException('O áudio gravado está vazio ou é curto demais.');
        }

        $response = $this->client()
            ->attach('audio_file', $contents, basename($turn->audio_path))
            ->post($this->transcriptionUrl($turn));

        if ($response->failed()) {
            $this->throwForFailedResponse($response);
        }

        try {
            $response->throw();
        } catch (ConnectionException $exception) {
            throw new TransientTranscriptionException($exception->getMessage(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response !== null) {
                $this->throwForFailedResponse($exception->response);
            }

            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        $transcript = $response->json('text');

        if (! is_string($transcript) || $transcript === '') {
            throw new RuntimeException('A transcrição retornou um texto vazio.');
        }

        return $transcript;
    }

    private function client(): PendingRequest
    {
        $client = Http::connectTimeout(config('rafael.transcriber.connect_timeout'))
            ->timeout(config('rafael.transcriber.timeout'));

        $apiKey = config('rafael.transcriber.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        return $client;
    }

    private function transcriptionLanguage(VoiceTurn $turn): string
    {
        return str($turn->locale)->before('-')->lower()->toString();
    }

    private function transcriptionUrl(VoiceTurn $turn): string
    {
        $baseUrl = config('rafael.transcriber.url');
        $query = http_build_query([
            'output' => 'json',
            'task' => 'transcribe',
            'language' => $this->transcriptionLanguage($turn),
        ]);

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.$query;
    }

    private function throwForFailedResponse(Response $response): never
    {
        $apiMessage = $response->json('error.message');
        $apiCode = $response->json('error.code');

        Log::warning('Transcription request failed.', [
            'status' => $response->status(),
            'code' => $apiCode,
            'message' => is_string($apiMessage) ? $apiMessage : $response->body(),
        ]);

        if ($response->status() === 401) {
            throw new PermanentTranscriptionException('Chave da API de transcrição inválida.');
        }

        if ($response->status() === 404) {
            throw new PermanentTranscriptionException('URL de transcrição inválida. Use TRANSCRIBER_URL terminando em /asr.');
        }

        if ($response->status() === 429 && $apiCode === 'insufficient_quota') {
            throw new PermanentTranscriptionException('Créditos da API de transcrição esgotados.');
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new TransientTranscriptionException(is_string($apiMessage) ? $apiMessage : $response->body());
        }

        if (is_string($apiMessage) && $apiMessage !== '') {
            throw new PermanentTranscriptionException($this->userFacingMessage($apiMessage));
        }

        throw new RuntimeException('Opa. Ocorreu um erro ao tentar no sistema. Código: '.$response->status());
    }

    private function userFacingMessage(string $apiMessage): string
    {
        return str($apiMessage)->limit(255, '')->toString();
    }
}
