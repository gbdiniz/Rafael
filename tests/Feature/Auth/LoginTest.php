<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('renders the login screen to a guest', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Entrar');
});

it('redirects to the conversation after valid credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertRedirect(route('conversation'));

    $this->assertAuthenticatedAs($user);
});

it('stays on the login screen when the credentials are wrong', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
    ]);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertInvalid([
            'email' => 'Essas credenciais não correspondem aos nossos registros.',
        ]);

    $this->assertGuest();
});

it('returns not found for the registration url', function () {
    $this->get('/register')->assertNotFound();
});

it('does not offer signup on the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Registrar')
        ->assertDontSee('Register')
        ->assertDontSee('Cadastr');
});
