<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('redirects a guest to login when streaming speech', function () {
    $this->post(route('speech.stream'))
        ->assertRedirect(route('login'));
});

it('streams the session line and stores no audio file', function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');

    config([
        'rafael.piper.url' => 'http://piper.test',
        'rafael.piper.token' => 'piper-token',
        'rafael.piper.connect_timeout' => 5,
        'rafael.piper.timeout' => 60,
    ]);

    Http::fake([
        'http://piper.test/synthesize/stream' => Http::response('RIFFfake', 200, [
            'Content-Type' => 'audio/wav',
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['rafael.speech' => 'Olá.'])
        ->post(route('speech.stream'), [
            'text' => 'isto não deve ser falado',
        ]);

    $response->assertOk();
    expect($response->streamedContent())->toBe('RIFFfake');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://piper.test/synthesize/stream'
            && $request['text'] === 'Olá.'
            && $request->hasHeader('Authorization', 'Bearer piper-token');
    });

    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('returns not found when there is no session line', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('speech.stream'), [
            'text' => 'Olá.',
        ])
        ->assertNotFound();

    Http::assertNothingSent();
});
