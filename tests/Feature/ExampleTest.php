<?php

use App\Models\User;
use Livewire\Livewire;

it('returns a successful response', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::conversation')
        ->assertSuccessful();
});
