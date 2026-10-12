<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeechStreamController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $text = $request->session()->get('rafael.speech');

        if (! is_string($text) || $text === '') {
            abort(404);
        }

        $pending = Http::connectTimeout((int) config('rafael.piper.connect_timeout', 5))
            ->timeout((int) config('rafael.piper.timeout', 60))
            ->withOptions(['stream' => true])
            ->accept('audio/wav');

        $token = config('rafael.piper.token');

        if (is_string($token) && $token !== '') {
            $pending = $pending->withToken($token);
        }

        $response = $pending->post(rtrim((string) config('rafael.piper.url'), '/').'/synthesize/stream', [
            'text' => $text,
        ]);

        if ($response->failed()) {
            abort(502);
        }

        $body = $response->toPsrResponse()->getBody();

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(8192);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
