<?php

use App\Models\User;

test('el registro público está cerrado', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Intruso',
        'email' => 'intruso@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'intruso@example.com')->exists())->toBeFalse();
});

test('la pantalla de login sigue disponible', function () {
    $this->get('/login')->assertOk();
});
