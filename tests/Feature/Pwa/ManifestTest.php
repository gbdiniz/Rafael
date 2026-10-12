<?php

it('sets the manifest start url to the conversation', function () {
    $this->get(route('pwa.manifest'))
        ->assertOk()
        ->assertJsonPath('start_url', '/');
});

it('does not name a separate inbox in the manifest', function () {
    $this->get(route('pwa.manifest'))
        ->assertOk()
        ->assertJsonMissing(['name' => 'inbox'])
        ->assertDontSee('inbox');
});

it('serves the service worker script', function () {
    $this->get(route('pwa.service-worker'))
        ->assertOk()
        ->assertSee('skipWaiting', false);
});
