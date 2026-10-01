<?php

use App\Models\User;
use Livewire\Livewire;

it('exposes start and stop clicks on the talk control', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('data-talk-control-click="toggle"', escape: false)
        ->assertSee('data-talk-control-state', escape: false);
});

it('does not stop the talk control on mouse leave', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('data-talk-control-ignores-mouseleave', escape: false)
        ->assertDontSee('@mouseleave', escape: false);
});

it('marks the recorder island with wire:ignore', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSeeHtml('wire:ignore');
});

it('marks the listening state for the bars', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSee('data-talk-listening-bars', escape: false)
        ->assertSee('data-talk-listening', escape: false);
});
